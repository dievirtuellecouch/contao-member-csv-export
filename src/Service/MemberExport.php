<?php

namespace Websailing\MemberExportBundle\Service;

use Contao\Backend;
use Contao\Environment;
use Contao\File;
use Contao\FilesModel;
use Contao\Input;
use Contao\Message;
use Contao\MemberModel;
use Contao\System;
use Contao\CoreBundle\Exception\ResponseException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls as XlsWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class MemberExport extends Backend
{
    public function generate(): string
    {
        if (Input::get('key') !== 'export') {
            return '';
        }

        if (Input::post('FORM_SUBMIT') === 'tl_member_export') {
            $cb = $GLOBALS['MEMBER_EXPORT_FORMATS'][Input::post('format')] ?? null;
            if (!$cb) {
                $this->reload();
            }
            try {
                if (\is_array($cb)) {
                    System::importStatic($cb[0])->{$cb[1]}((bool) Input::post('headerFields'), (bool) Input::post('raw'));
                } elseif (\is_callable($cb)) {
                    $cb((bool) Input::post('headerFields'), (bool) Input::post('raw'));
                }
            } catch (\Throwable $e) {
                if ($e instanceof ResponseException) {
                    throw $e; // let Contao handle the file response
                }
                Message::addError($e->getMessage());
            }
            $this->reload();
        }

        $options = '';
        foreach (array_keys($GLOBALS['MEMBER_EXPORT_FORMATS']) as $format) {
            $label = $GLOBALS['TL_LANG']['tl_member']['export_format_ref'][$format] ?? strtoupper($format);
            $options .= '<option value="'.$format.'">'.$label.'</option>';
        }

        $backUrl = \Contao\StringUtil::ampersand(str_replace('&key=export', '', Environment::get('request')));
        $backTitle = \Contao\StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['backBTTitle'] ?? 'Back');
        $backLabel = $GLOBALS['TL_LANG']['MSC']['backBT'] ?? 'Back';
        $headline = $GLOBALS['TL_LANG']['tl_member']['export'][1] ?? 'Mitglieder exportieren';
        $desc = $GLOBALS['TL_LANG']['tl_member']['export_description'] ?? '';
        $formAction = \Contao\StringUtil::ampersand(Environment::get('request'), true);
        $tokenValue = htmlspecialchars(System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue(), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
        $labelFormat = $GLOBALS['TL_LANG']['tl_member']['export_format'] ?? 'Format';
        $labelHeader = $GLOBALS['TL_LANG']['tl_member']['export_headerFields'] ?? 'Spaltenüberschriften ausgeben';
        $labelRaw = $GLOBALS['TL_LANG']['tl_member']['export_raw'] ?? 'Rohdaten ausgeben';
        $submitLabel = \Contao\StringUtil::specialchars(($GLOBALS['TL_LANG']['tl_member']['export'][0] ?? 'Export starten'));

        $html = '';
        $html .= '<div id="tl_buttons">';
        $html .= '<a href="'.$backUrl.'" class="header_back" title="'.$backTitle.'" accesskey="b">'.$backLabel.'</a>';
        $html .= '</div>';
        $html .= '<h2 class="sub_headline">'.$headline.'</h2>';
        $html .= '<div class="tl_formbody_edit">'.$desc.'</div>';
        $html .= Message::generate();
        $html .= '<form action="'.$formAction.'" id="tl_member_export" class="tl_form" method="post" enctype="multipart/form-data">';
        $html .= '<div class="tl_formbody_edit">';
        $html .= '<input type="hidden" name="FORM_SUBMIT" value="tl_member_export">';
        $html .= '<input type="hidden" name="REQUEST_TOKEN" value="'.$tokenValue.'">';
        $html .= '<div class="tl_tbox">';
        $html .= '<h3><label for="format">'.$labelFormat.'</label></h3>';
        $html .= '<select name="format" id="format" class="tl_select" onfocus="Backend.getScrollOffset()">'.$options.'</select>';
        $html .= '<div class="tl_checkbox_single_container">';
        $html .= '<input type="checkbox" name="headerFields" id="headerFields" class="tl_checkbox" value="1" onfocus="Backend.getScrollOffset()">';
        $html .= '<label for="headerFields">'.$labelHeader.'</label>';
        $html .= '</div>';
        $html .= '<div class="tl_checkbox_single_container">';
        $html .= '<input type="checkbox" name="raw" id="raw" class="tl_checkbox" value="1" onfocus="Backend.getScrollOffset()">';
        $html .= '<label for="raw">'.$labelRaw.'</label>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="tl_formbody_submit">';
        $html .= '<div class="tl_submit_container">';
        $html .= '<input type="submit" name="export" id="export" class="tl_submit" accesskey="e" value="'.$submitLabel.'">';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</form>';
        return $html;
    }

    public function exportCsv(bool $header, bool $raw): void
    {
        $rows = $this->buildRows($header, $raw);
        $filename = $this->getTmpPath('csv'); // relative path for Contao\File
        $projectDir = (string) System::getContainer()->getParameter('kernel.project_dir');
        $absFile = $projectDir.'/'.$filename;
        $fp = @fopen($absFile, 'wb');
        // UTF-8 BOM for Excel compatibility
        if ($fp === false) {
            Message::addError('Exportdatei konnte nicht erstellt werden: '.$absFile);
            return;
        }
        fwrite($fp, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            $line = array_map(fn($v) => is_scalar($v) ? (string) $v : (is_array($v) ? implode(', ', $v) : ''), $row);
            fputcsv($fp, $line, ';');
        }
        fclose($fp);
        (new File($filename))->sendToBrowser();
    }

    public function exportExcel5(bool $header, bool $raw): void
    {
        $rows = $this->buildRows($header, $raw);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $r = 1;
        foreach ($rows as $row) {
            $c = 1;
            foreach ($row as $val) {
                $sheet->setCellValueByColumnAndRow($c, $r, is_scalar($val) ? $val : (is_array($val) ? implode(', ', $val) : ''));
                $c++;
            }
            $r++;
        }
        $filename = $this->getTmpPath('xls'); // relative
        $projectDir = (string) System::getContainer()->getParameter('kernel.project_dir');
        $absFile = $projectDir.'/'.$filename;
        try {
            (new XlsWriter($spreadsheet))->save($absFile);
        } catch (\Throwable $e) {
            Message::addError('Export fehlgeschlagen: '.$e->getMessage());
            return;
        }
        (new File($filename))->sendToBrowser();
    }

    public function exportExcel2007(bool $header, bool $raw): void
    {
        $rows = $this->buildRows($header, $raw);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $r = 1;
        foreach ($rows as $row) {
            $c = 1;
            foreach ($row as $val) {
                $sheet->setCellValueByColumnAndRow($c, $r, is_scalar($val) ? $val : (is_array($val) ? implode(', ', $val) : ''));
                $c++;
            }
            $r++;
        }
        $filename = $this->getTmpPath('xlsx'); // relative
        $projectDir = (string) System::getContainer()->getParameter('kernel.project_dir');
        $absFile = $projectDir.'/'.$filename;
        try {
            (new XlsxWriter($spreadsheet))->save($absFile);
        } catch (\Throwable $e) {
            Message::addError('Export fehlgeschlagen: '.$e->getMessage());
            return;
        }
        (new File($filename))->sendToBrowser();
    }

    private function buildRows(bool $header, bool $raw): array
    {
        $members = MemberModel::findAll();
        if (null === $members) {
            $this->reload();
        }

        $fields = array_keys($GLOBALS['TL_DCA']['tl_member']['fields'] ?? []);
        $rows = [];

        if ($header) {
            $labels = [];
            foreach ($fields as $field) {
                $cfg = $GLOBALS['TL_DCA']['tl_member']['fields'][$field] ?? [];
                $labels[] = ($raw || empty($cfg['label'][0])) ? $field : $cfg['label'][0];
            }
            $rows[] = $labels;
        }

        // Optional Haste formatter for nicer values
        $formatter = null;
        try {
            $formatter = System::getContainer()->get(\Codefog\HasteBundle\Formatter::class);
        } catch (\Throwable $e) {}

        while ($members->next()) {
            $row = $members->row();
            $out = [];
            foreach ($fields as $field) {
                $val = $row[$field] ?? null;
                if (!$raw && $formatter) {
                    try {
                        $val = $formatter->dcaValue('tl_member', $field, $val);
                    } catch (\Throwable $e) {}
                }
                // Resolve file UUIDs to paths
                if (!$raw && $val) {
                    try {
                        if (\is_string($val) && \Contao\Validator::isUuid($val)) {
                            if ($file = FilesModel::findByUuid($val)) { $val = $file->path; }
                        } elseif (\is_string($val) && strlen($val) === 16) {
                            $uuid = \Contao\StringUtil::binToUuid($val);
                            if ($uuid && ($file = FilesModel::findByUuid($uuid))) { $val = $file->path; }
                        }
                    } catch (\Throwable $e) {}
                }
                $out[] = $val;
            }
            $rows[] = $out;
        }
        return $rows;
    }

    private function getTmpPath(string $extension): string
    {
        // Build an absolute directory for writing, but return a path relative
        // to the project root for Contao\File which expects relative paths.
        $projectDir = (string) System::getContainer()->getParameter('kernel.project_dir');
        $relDir = 'var/tmp';
        $absDir = $projectDir.'/'.$relDir;
        if (!is_dir($absDir)) { @mkdir($absDir, 0775, true); }
        $tmp = tempnam($absDir, 'member_export_');
        $absFile = $tmp.'.'.$extension;
        @rename($tmp, $absFile);
        return $relDir.'/'.basename($absFile);
    }
}
