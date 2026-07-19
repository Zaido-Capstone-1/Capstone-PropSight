<?php
/**
 * lib/report_lib.php
 * Shared "generate report" engine used by endpoints/generate_report.php
 * for the Financial, Booking, and Occupancy report pages.
 *
 * Formats supported:
 *   - pdf  : branded, paginated executive-summary document (PSReportPDF, built on FPDF)
 *   - xlsx : real .xlsx workbook (hand-rolled writer, no Composer / PhpSpreadsheet needed)
 *   - csv  : plain CSV, Excel-safe (UTF-8 BOM)
 *
 * No external dependencies beyond FPDF (vendored in lib/fpdf/fpdf.php, public domain)
 * and PHP's built-in ZipArchive extension (bundled with PHP/XAMPP).
 */

require_once __DIR__ . '/fpdf/fpdf.php';

/* ─────────────────────────────────────────────────────────────────────────
   Small shared helpers
   ───────────────────────────────────────────────────────────────────────── */

/** Validates & normalizes a from/to date range coming from the request. */
function ps_report_parse_range(array $params): array
{
    $today = date('Y-m-d');
    $from = $params['from'] ?? date('Y-m-01');
    $to = $params['to'] ?? $today;

    $fromTs = strtotime($from);
    $toTs = strtotime($to);

    if ($fromTs === false || $toTs === false) {
        throw new InvalidArgumentException('Invalid date range.');
    }
    if ($fromTs > $toTs) {
        [$fromTs, $toTs] = [$toTs, $fromTs];
    }
    // Cap range to 5 years to keep reports sane.
    if ($toTs - $fromTs > 5 * 365 * 86400) {
        throw new InvalidArgumentException('Date range too large (max 5 years).');
    }

    return [date('Y-m-d', $fromTs), date('Y-m-d', $toTs)];
}

function ps_report_period_label(string $from, string $to): string
{
    $f = date('M j, Y', strtotime($from));
    $t = date('M j, Y', strtotime($to));
    return $f === $t ? $f : "$f – $t";
}

/** PHP peso formatting for UTF-8 destinations (CSV/XLSX). Whole pesos — no trailing .00 cents. */
function ps_money(float $amount): string
{
    return '₱ ' . number_format($amount, 0);
}

/** PDF money formatting — the embedded DejaVu font supports the peso glyph directly. */
function ps_money_pdf(float $amount): string
{
    return '₱ ' . number_format($amount, 0);
}

/* ─────────────────────────────────────────────────────────────────────────
   CSV export
   ───────────────────────────────────────────────────────────────────────── */

/**
 * Streams a CSV file and exits.
 * $sections: array of ['title' => string|null, 'headers' => array, 'rows' => array<array>]
 */
function ps_send_csv(string $filename, string $reportTitle, string $periodLabel, array $sections): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: public');
    header('Cache-Control: max-age=0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel renders ₱ / accents correctly

    fputcsv($out, [$reportTitle]);
    fputcsv($out, ['Period: ' . $periodLabel]);
    fputcsv($out, ['Generated: ' . date('M j, Y g:i A')]);

    foreach ($sections as $section) {
        fputcsv($out, []);
        if (!empty($section['title'])) {
            fputcsv($out, [$section['title']]);
        }
        if (!empty($section['headers'])) {
            fputcsv($out, $section['headers']);
        }
        foreach ($section['rows'] as $row) {
            fputcsv($out, $row);
        }
    }

    fclose($out);
    exit;
}

/* ─────────────────────────────────────────────────────────────────────────
   XLSX export — minimal, dependency-free writer (ZipArchive only)
   ───────────────────────────────────────────────────────────────────────── */

function ps_xlsx_col(int $index): string
{
    $letters = '';
    $index++;
    while ($index > 0) {
        $rem = ($index - 1) % 26;
        $letters = chr(65 + $rem) . $letters;
        $index = intdiv($index - 1, 26);
    }
    return $letters;
}

function ps_xlsx_escape(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * Streams a real .xlsx workbook and exits.
 * $sections: array of ['title' => string|null, 'headers' => array, 'rows' => array<array>]
 * Each section is written into the same single sheet, stacked with a blank row between,
 * so the whole report opens as one readable spreadsheet.
 */
function ps_send_xlsx(string $filename, string $reportTitle, string $periodLabel, array $sections): void
{
    if (!class_exists('ZipArchive')) {
        // Fallback so the download never hard-fails even if the zip extension is missing.
        ps_send_csv(str_replace('.xlsx', '.csv', $filename), $reportTitle, $periodLabel, $sections);
        return;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'ps_xlsx_');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);

    $zip->addEmptyDir('_rels');
    $zip->addEmptyDir('xl');
    $zip->addEmptyDir('xl/_rels');
    $zip->addEmptyDir('xl/worksheets');

    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
        '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
        '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
        '</Types>');

    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
        '</Relationships>');

    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
        '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
        '</Relationships>');

    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" ' .
        'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets>' .
        '</workbook>');

    // Styles: 0 = normal, 1 = bold report title, 2 = bold table header w/ fill, 3 = section title
    $zip->addFromString('xl/styles.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<fonts count="3">' .
        '<font><sz val="11"/><name val="Calibri"/></font>' .
        '<font><b/><sz val="14"/><name val="Calibri"/><color rgb="FF0A1628"/></font>' .
        '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>' .
        '</fonts>' .
        '<fills count="3">' .
        '<fill><patternFill patternType="none"/></fill>' .
        '<fill><patternFill patternType="gray125"/></fill>' .
        '<fill><patternFill patternType="solid"><fgColor rgb="FF0A1628"/><bgColor indexed="64"/></patternFill></fill>' .
        '</fills>' .
        '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
        '<cellXfs count="4">' .
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .
        '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>' .
        '<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>' .
        '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>' .
        '</cellXfs>' .
        '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>' .
        '</styleSheet>');

    $sheetRows = '';
    $r = 1;

    $writeCell = function (int $r, int $c, $value, int $style = 0) use (&$sheetRows) {
        $ref = ps_xlsx_col($c) . $r;
        if ($value === '' || $value === null) {
            $sheetRows .= "<c r=\"$ref\" s=\"$style\"/>";
            return;
        }
        // Numeric values are written as real numbers so Excel can sum/format them.
        $isNumeric = is_numeric($value) && !preg_match('/^0[0-9]/', (string) $value);
        if ($isNumeric) {
            $sheetRows .= "<c r=\"$ref\" s=\"$style\"><v>" . (0 + $value) . "</v></c>";
        } else {
            $text = ps_xlsx_escape((string) $value);
            $sheetRows .= "<c r=\"$ref\" t=\"inlineStr\" s=\"$style\"><is><t xml:space=\"preserve\">$text</t></is></c>";
        }
    };

    $writeRow = function (int $r, array $values, int $style = 0) use (&$sheetRows, $writeCell) {
        $sheetRows .= "<row r=\"$r\">";
        foreach (array_values($values) as $c => $v) {
            $writeCell($r, $c, $v, $style);
        }
        $sheetRows .= '</row>';
    };

    $writeRow($r++, [$reportTitle], 1);
    $writeRow($r++, ['Period: ' . $periodLabel]);
    $writeRow($r++, ['Generated: ' . date('M j, Y g:i A')]);

    foreach ($sections as $section) {
        $r++; // blank spacer row
        if (!empty($section['title'])) {
            $writeRow($r++, [$section['title']], 3);
        }
        if (!empty($section['headers'])) {
            $writeRow($r++, $section['headers'], 2);
        }
        foreach ($section['rows'] as $row) {
            $writeRow($r++, $row);
        }
    }

    $maxCols = 1;
    foreach ($sections as $section) {
        if (!empty($section['headers'])) {
            $maxCols = max($maxCols, count($section['headers']));
        }
    }
    $dimEnd = ps_xlsx_col(max(0, $maxCols - 1)) . ($r - 1);

    $colsXml = '<cols>';
    for ($i = 0; $i < $maxCols; $i++) {
        $colsXml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="22" customWidth="1"/>';
    }
    $colsXml .= '</cols>';

    $zip->addFromString('xl/worksheets/sheet1.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<dimension ref="A1:' . $dimEnd . '"/>' .
        $colsXml .
        '<sheetData>' . $sheetRows . '</sheetData>' .
        '</worksheet>');

    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmp));
    header('Pragma: public');
    header('Cache-Control: max-age=0');
    readfile($tmp);
    unlink($tmp);
    exit;
}

/* ─────────────────────────────────────────────────────────────────────────
   PDF export — branded executive-summary document
   ───────────────────────────────────────────────────────────────────────── */

class PSReportPDF extends FPDF
{
    public string $reportTitle = 'Report';
    public string $periodLabel = '';
    private array $tableHeaders = [];
    private array $tableWidths = [];
    private array $tableAligns = [];
    private bool $tableFillToggle = false;

    function __construct(string $orientation = 'P', string $unit = 'mm', $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size);
        $this->AddFont('DejaVu', '', 'dejavu.json');
        $this->AddFont('DejaVu', 'B', 'dejavub.json');
    }

    /**
     * Converts UTF-8 text to the single-byte encoding used by the embedded DejaVu font
     * (cp1252, plus the peso sign ₱ remapped onto byte 0x81 — unused in standard cp1252).
     * This keeps ₱ intact instead of the "PHP" fallback needed for FPDF's core Latin-1 fonts.
     */
    private function enc(string $s): string
    {
        $s = str_replace("\u{20B1}", "\x01", $s); // protect ₱ through the transliteration pass
        $converted = @iconv('UTF-8', 'CP1252//TRANSLIT', $s);
        if ($converted === false) {
            $converted = $s;
        }
        return str_replace("\x01", "\x81", $converted);
    }

    function Header(): void
    {
        $this->SetFillColor(10, 22, 40);
        $this->Rect(0, 0, 210, 24, 'F');

        $logo = __DIR__ . '/../assets/images/final logo.png';
        if (is_file($logo)) {
            try {
                $this->Image($logo, 12, 5, 0, 14);
            } catch (\Throwable $e) {
                // ignore broken/incompatible image, header text still renders
            }
        }

        $this->SetXY(30, 5);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('DejaVu', 'B', 16);
        $this->Cell(0, 8, $this->enc('PropSight'), 0, 1, 'L');

        $this->SetXY(30, 13);
        $this->SetFont('DejaVu', 'B', 11);
        $this->SetTextColor(222, 175, 55);
        $this->Cell(0, 6, $this->enc($this->reportTitle), 0, 1, 'L');

        $this->SetXY(30, 18.5);
        $this->SetFont('DejaVu', '', 8.5);
        $this->SetTextColor(200, 210, 225);
        $this->Cell(0, 5, $this->enc('Period: ' . $this->periodLabel), 0, 1, 'L');

        $this->SetY(30);
        $this->SetTextColor(20, 20, 20);
    }

    function Footer(): void
    {
        $this->SetY(-15);
        $this->SetDrawColor(222, 175, 55);
        $this->SetLineWidth(0.4);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->SetY(-12);
        $this->SetFont('DejaVu', '', 8);
        $this->SetTextColor(120, 130, 145);
        $this->Cell(120, 8, $this->enc('Generated ' . date('M j, Y g:i A') . '  •  PropSight — Confidential'), 0, 0, 'L');
        $this->Cell(60, 8, $this->enc('Page ' . $this->PageNo() . ' / {nb}'), 0, 0, 'R');
    }

    /** Section heading with a gold underline. */
    function SectionTitle(string $text): void
    {
        if ($this->GetY() > 250) {
            $this->AddPage();
        }
        $this->Ln(3);
        $this->SetFont('DejaVu', 'B', 12.5);
        $this->SetTextColor(10, 22, 40);
        $this->Cell(0, 7, $this->enc($text), 0, 1, 'L');
        $this->SetDrawColor(222, 175, 55);
        $this->SetLineWidth(0.6);
        $y = $this->GetY() + 1;
        $this->Line(15, $y, 195, $y);
        $this->SetLineWidth(0.2);
        $this->Ln(5);
        $this->SetTextColor(20, 20, 20);
    }

    /** Word-wraps $text into lines that each fit within $maxW at the current font. */
    private function wrapLines(string $text, float $maxW): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $test = $current === '' ? $word : $current . ' ' . $word;
            if ($this->GetStringWidth($test) <= $maxW || $current === '') {
                $current = $test;
            } else {
                $lines[] = $current;
                $current = $word;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        return $lines ?: [''];
    }

    /** A row of up to 4 stat cards: [ ['label' => ..., 'value' => ...], ... ] */
    function StatCards(array $items): void
    {
        $n = max(1, count($items));
        $perRow = min($n, 4);
        $gap = 4;
        $w = (180 - $gap * ($perRow - 1)) / $perRow;
        $maxW = $w - 6;
        $lineH = 5.2;
        $x0 = 15;
        $y0 = $this->GetY();

        $chunks = array_chunk(array_values($items), 4);
        foreach ($chunks as $chunk) {
            // First pass: figure out the font size + wrapped lines for every card in this row,
            // then size the whole row to the tallest card so nothing overlaps.
            $prepared = [];
            $rowH = 14; // minimum card height (label + 1 line)
            foreach ($chunk as $item) {
                $value = (string) $item['value'];
                $fontSize = 12.5;
                $this->SetFont('DejaVu', 'B', $fontSize);
                $lines = [$value];
                if ($this->GetStringWidth($value) > $maxW) {
                    // Shrink first…
                    while ($fontSize > 8 && $this->GetStringWidth($value) > $maxW) {
                        $fontSize -= 0.5;
                        $this->SetFont('DejaVu', 'B', $fontSize);
                    }
                    // …then wrap onto as many lines as needed at that size.
                    if ($this->GetStringWidth($value) > $maxW) {
                        $lines = $this->wrapLines($value, $maxW);
                    }
                }
                $cardH = 8 + count($lines) * $lineH + 3;
                $rowH = max($rowH, $cardH);
                $prepared[] = ['label' => $item['label'], 'lines' => $lines, 'fontSize' => $fontSize];
            }

            if ($y0 + $rowH > 275) {
                $this->AddPage();
                $y0 = $this->GetY();
            }

            foreach ($prepared as $col => $card) {
                $x = $x0 + $col * ($w + $gap);
                $this->SetFillColor(245, 247, 251);
                $this->SetDrawColor(219, 226, 240);
                $this->Rect($x, $y0, $w, $rowH, 'DF');

                $this->SetXY($x + 3, $y0 + 3);
                $this->SetFont('DejaVu', '', 8);
                $this->SetTextColor(100, 112, 130);
                $this->Cell($w - 6, 4, $this->enc($card['label']), 0, 2, 'L');

                $this->SetFont('DejaVu', 'B', $card['fontSize']);
                $this->SetTextColor(10, 22, 40);
                foreach ($card['lines'] as $line) {
                    $this->SetX($x + 3);
                    $this->Cell($w - 6, $lineH, $this->enc($line), 0, 2, 'L');
                }
            }

            $y0 += $rowH + $gap;
        }

        $this->SetXY($x0, $y0);
        $this->SetTextColor(20, 20, 20);
    }

    /** Draws (and remembers, for page-break repeats) a table header row. */
    private function drawTableHeader(): void
    {
        $this->SetFont('DejaVu', 'B', 8.5);
        $this->SetFillColor(10, 22, 40);
        $this->SetTextColor(255, 255, 255);
        foreach ($this->tableHeaders as $i => $h) {
            $this->Cell($this->tableWidths[$i], 7.5, $this->enc($h), 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetFont('DejaVu', '', 8.5);
        $this->SetTextColor(25, 30, 40);
    }

    /**
     * Renders a simple bordered table with a repeating header on page breaks.
     * $widths defaults to equal columns across the usable 180mm width.
     */
    function Table(array $headers, array $rows, ?array $widths = null, ?array $aligns = null): void
    {
        $n = count($headers);
        $this->tableHeaders = $headers;
        $this->tableWidths = $widths ?: array_fill(0, $n, 180 / max(1, $n));
        $this->tableAligns = $aligns ?: array_fill(0, $n, 'L');
        $this->tableFillToggle = false;

        $this->drawTableHeader();

        if (empty($rows)) {
            $this->SetFont('DejaVu', '', 8.5);
            $this->SetTextColor(140, 140, 140);
            $this->Cell(array_sum($this->tableWidths), 8, $this->enc('No data for this period.'), 1, 1, 'C');
            $this->SetTextColor(20, 20, 20);
            $this->Ln(2);
            return;
        }

        foreach ($rows as $row) {
            if ($this->GetY() > 268) {
                $this->AddPage();
                $this->drawTableHeader();
            }
            $this->tableFillToggle = !$this->tableFillToggle;
            $this->SetFillColor(245, 247, 251);
            foreach (array_values($row) as $i => $val) {
                $align = $this->tableAligns[$i] ?? 'L';
                $this->Cell($this->tableWidths[$i], 7, $this->enc((string) $val), 1, 0, $align, $this->tableFillToggle);
            }
            $this->Ln();
        }
        $this->Ln(3);
    }
}

/**
 * Builds and streams the branded PDF, then exits.
 * $sections: array of:
 *   ['type' => 'stats', 'title' => ?string, 'items' => [['label'=>..,'value'=>..], ...]]
 *   ['type' => 'table', 'title' => ?string, 'headers' => [...], 'rows' => [...], 'widths' => ?[...], 'aligns' => ?[...]]
 */
function ps_send_pdf(string $filename, string $reportTitle, string $periodLabel, array $sections): void
{
    $pdf = new PSReportPDF();
    $pdf->reportTitle = $reportTitle;
    $pdf->periodLabel = $periodLabel;
    $pdf->AliasNbPages();
    $pdf->AddPage();

    foreach ($sections as $section) {
        if (!empty($section['title'])) {
            $pdf->SectionTitle($section['title']);
        }
        if (($section['type'] ?? '') === 'stats') {
            $pdf->StatCards($section['items']);
        } elseif (($section['type'] ?? '') === 'table') {
            $pdf->Table($section['headers'], $section['rows'], $section['widths'] ?? null, $section['aligns'] ?? null);
        }
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $pdf->Output('S');
    exit;
}