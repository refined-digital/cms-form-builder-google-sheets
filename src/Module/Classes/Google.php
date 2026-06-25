<?php

namespace RefinedDigital\GoogleSheets\Module\Classes;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;

class Google
{
    protected Client $client;

    protected Sheets $service;

    protected ?string $spreadsheetId;

    public function __construct()
    {
        $this->client = new Client;
        $this->client->setApplicationName('Google Sheets Sync');

        if ($key = config('google-sheets.api_key')) {
            $this->client->setDeveloperKey($key);
        }

        if ($authConfig = config('google-sheets.auth_config')) {
            $this->client->setAuthConfig(storage_path('app/'.$authConfig));
        }

        $this->client->addScope(Sheets::SPREADSHEETS);

        $this->service = new Sheets($this->client);
        $this->spreadsheetId = config('google-sheets.spreadsheet_id');
    }

    /**
     * Append the given rows below the last populated row of the sheet.
     *
     * @param  array  $values  array of rows, each row an array of cell values
     */
    public function put(array $values): void
    {
        $dimensions = $this->getDimensions();
        if ($dimensions->error) {
            throw new \RuntimeException($dimensions->message);
        }

        $row = $dimensions->rowCount + 1;
        $column = $dimensions->colCount;

        // the Sheets API needs values + each row to be JSON arrays, not objects;
        // re-index defensively so gapped keys never serialize as {"0":..,"2":..}
        $values = array_map('array_values', array_values($values));

        $body = new ValueRange(['values' => $values]);
        $range = 'A'.$row.':'.$column.$row;

        $this->service->spreadsheets_values->update(
            $this->spreadsheetId,
            $range,
            $body,
            ['valueInputOption' => 'RAW']
        );
    }

    /**
     * Determine the next empty row and the right-most column letter by reading
     * the sheet's first column and first row.
     */
    private function getDimensions(): object
    {
        $rowDimensions = $this->service->spreadsheets_values->batchGet(
            $this->spreadsheetId,
            ['ranges' => '!A:A', 'majorDimension' => 'COLUMNS']
        );
        $rowMeta = $rowDimensions->getValueRanges()[0]->values;
        if (! $rowMeta) {
            return (object) ['error' => true, 'message' => 'missing row data'];
        }

        $colDimensions = $this->service->spreadsheets_values->batchGet(
            $this->spreadsheetId,
            ['ranges' => '!1:1', 'majorDimension' => 'ROWS']
        );
        $colMeta = $colDimensions->getValueRanges()[0]->values;
        if (! $colMeta) {
            return (object) ['error' => true, 'message' => 'missing column data'];
        }

        return (object) [
            'error' => false,
            'rowCount' => count($rowMeta[0]),
            'colCount' => $this->colLengthToColumnAddress(count($colMeta[0])),
        ];
    }

    private function colLengthToColumnAddress(int $number): ?string
    {
        if ($number <= 0) {
            return null;
        }

        $letter = '';
        while ($number > 0) {
            $temp = ($number - 1) % 26;
            $letter = chr($temp + 65).$letter;
            $number = (int) (($number - $temp - 1) / 26);
        }

        return $letter;
    }
}
