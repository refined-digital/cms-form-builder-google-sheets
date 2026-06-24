<?php

namespace RefinedDigital\GoogleSheets\Module\Classes;

use Carbon\Carbon;
use RefinedDigital\FormBuilder\Module\Contracts\FormBuilderIntegrationInterface;
use RefinedDigital\FormBuilder\Module\Http\Repositories\FormBuilderRepository;
use RefinedDigital\FormBuilder\Module\Http\Repositories\FormsRepository;

class Process implements FormBuilderIntegrationInterface
{
    /**
     * Append the submission as a row to the configured Google Sheet.
     *
     * Notifications are the form's responsibility — this MUST NOT send email.
     * A sheet failure is reported but never aborts the submission, so we always
     * return null (success) regardless.
     */
    public function process($request, $form, $settings)
    {
        $formRepo = new FormsRepository(new FormBuilderRepository);

        // each field's merge_field is the column it maps to; column order follows
        // field position, so admins order merge_field'd fields to match the sheet
        $fields = $formRepo->formatWithMergeFields($request, $form);

        $row = [];
        if (config('google-sheets.timestamp')) {
            $row[] = Carbon::now('UTC')->format('Y-m-d H:i:s');
        }
        foreach ($fields as $value) {
            $row[] = mb_convert_encoding((string) $value, 'UTF-8');
        }

        try {
            (new Google)->put([$row]);
        } catch (\Exception $e) {
            // swallow sheet errors so a failed sync never blocks the submission
            report($e);
        }

        return null;
    }
}
