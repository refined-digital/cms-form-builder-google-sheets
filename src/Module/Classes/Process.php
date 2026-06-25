<?php

namespace RefinedDigital\GoogleSheets\Module\Classes;

use Carbon\Carbon;
use RefinedDigital\FormBuilder\Module\Contracts\FormBuilderIntegrationInterface;
use RefinedDigital\FormBuilder\Module\Http\Repositories\FormBuilderRepository;
use RefinedDigital\FormBuilder\Module\Http\Repositories\FormsRepository;

class Process implements FormBuilderIntegrationInterface
{
    // synthetic column the panel can place anywhere in the order
    const DATE_KEY = '__date';

    /**
     * Append the submission as a row to the configured Google Sheet.
     *
     * Columns come from the per-form integration config (config.fields = an
     * ordered list of [{key, enabled}]); the special key __date renders a UTC
     * timestamp. When nothing is configured we fall back to the form's
     * merge_field'd fields, with a leading timestamp from the global config.
     *
     * Notifications are the form's responsibility — this MUST NOT send email.
     * A sheet failure is reported but never aborts the submission, so we always
     * return null (success) regardless.
     */
    public function process($request, $form, $settings)
    {
        $config = $settings['config'] ?? [];
        $configured = collect($config['fields'] ?? [])->where('enabled', true);

        if ($configured->isNotEmpty()) {
            // values() re-indexes: the where() above leaves gapped keys, which
            // would serialize as a JSON object and the Sheets API rejects it
            $row = $configured->map(function ($field) use ($request) {
                if ($field['key'] === self::DATE_KEY) {
                    return Carbon::now('UTC')->format('Y-m-d H:i:s');
                }

                return $this->clean($request->get($field['key']));
            })->values()->all();
        } else {
            // fallback: every merge_field'd field, in field position order
            $row = [];
            if (config('google-sheets.timestamp')) {
                $row[] = Carbon::now('UTC')->format('Y-m-d H:i:s');
            }
            $formRepo = new FormsRepository(new FormBuilderRepository);
            foreach ($formRepo->formatWithMergeFields($request, $form) as $value) {
                $row[] = $this->clean($value);
            }
        }

        try {
            (new Google)->put([$row]);
        } catch (\Exception $e) {
            // swallow sheet errors so a failed sync never blocks the submission
            report($e);
        }

        return null;
    }

    protected function clean($value): string
    {
        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        return mb_convert_encoding((string) $value, 'UTF-8');
    }
}
