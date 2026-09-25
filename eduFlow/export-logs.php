<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  exit('Unauthorized');
}
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$search = trim($_GET['search'] ?? '');
$section = trim($_GET['section'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$where = [];
$params = [];
$types = '';
if ($search) {
  $s = "%$search%";
  $where[] = "(u.full_name LIKE ? OR al.action LIKE ?)";
  $params[] = $s;
  $params[] = $s;
  $types .= 'ss';
}
if ($section) {
  $where[] = "al.section=?";
  $params[] = $section;
  $types .= 's';
}
if ($dateFrom) {
  $where[] = "DATE(al.created_at)>=?";
  $params[] = $dateFrom;
  $types .= 's';
}
if ($dateTo) {
  $where[] = "DATE(al.created_at)<=?";
  $params[] = $dateTo;
  $types .= 's';
}
$wSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$sql = "SELECT u.full_name,al.action,al.section,al.ip_address,al.created_at FROM activity_logs al JOIN users u ON al.user_id=u.id $wSQL ORDER BY al.created_at DESC LIMIT 5000";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Build XLSX using ZipArchive (Office Open XML)
$sectionColors = ['users' => '4472C4', 'topics' => '70AD47', 'assignments' => 'ED7D31', 'submissions' => 'FFC000', 'batches' => '5B9BD5', 'courses' => 'A5A5A5', 'announcements' => 'FF0000', 'activity_logs' => '7030A0'];
$statusColors = ['active' => '70AD47', 'inactive' => 'FF0000', 'graded' => '4472C4', 'submitted' => 'FFC000', 'returned' => 'ED7D31'];

// Shared strings
$strings = [];
$strIndex = [];

function addStr(string $s): int
{
  global $strings, $strIndex;

  if (!isset($strIndex[$s])) {
    $strIndex[$s] = count($strings);
    $strings[] = $s;
  }

  return $strIndex[$s];
}

// Build rows
$dataRows = [];
foreach ($rows as $row) {
  $dataRows[] = [$row['full_name'], $row['action'], $row['section'] ?? '', $row['ip_address'] ?? '', $row['created_at'] ?? ''];
}

// XML strings for shared strings
function xmlEsc($s)
{
  return htmlspecialchars($s, ENT_XML1, 'UTF-8');
}

// Build sharedStrings.xml
$headers = ['User', 'Action', 'Section', 'IP Address', 'Timestamp'];
foreach ($headers as $h) addStr($h);
foreach ($dataRows as $r) foreach ($r as $cell) addStr((string)$cell);
$ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
foreach ($strings as $s) $ssXml .= '<si><t xml:space="preserve">' . xmlEsc($s) . '</t></si>';
$ssXml .= '</sst>';

// Styles XML - define fills, fonts, cellXfs
// xfId 0=default, 1=header(navy bg, white bold), 2..N=section colors
$fills = '<fills count="' . (3 + count($sectionColors)) . '"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>';
$fills .= '<fill><patternFill patternType="solid"><fgColor rgb="FF1E3A5F"/><bgColor indexed="64"/></patternFill></fill>'; // header dark navy
// section fills
$secFillIdx = [];
$idx = 3;
foreach ($sectionColors as $sec => $hex) {
  $fills .= '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $hex . '"/><bgColor indexed="64"/></patternFill></fill>';
  $secFillIdx[$sec] = $idx++;
};
$fills .= '</fills>';
$fonts = '<fonts count="3"><font><sz val="11"/><color theme="1"/><name val="Calibri"/></font><font><b/><sz val="12"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><sz val="10"/><color theme="1"/><name val="Calibri"/></font></fonts>';
$borders = '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD0D7E0"/></left><right style="thin"><color rgb="FFD0D7E0"/></right><top style="thin"><color rgb="FFD0D7E0"/></top><bottom style="thin"><color rgb="FFD0D7E0"/></bottom><diagonal/></border></borders>';
// Cell formats: 0=default, 1=header(fillId=2,fontId=1,border=1,align center), 2=body(border=1), 3..=section row highlight
$xfs = '<cellXfs count="' . (3 + count($sectionColors)) . '"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
  . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
  . '<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>';
foreach ($sectionColors as $sec => $hex) {
  $xfs .= '<xf numFmtId="0" fontId="2" fillId="' . $secFillIdx[$sec] . '" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>';
}
$xfs .= '</cellXfs>';
$styleXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="0"><numFmt numFmtId="0" formatCode="General"/></numFmts>' . $fonts . $fills . $borders . $xfs . '</styleSheet>';

// Build sheet data
function colLetter($n)
{
  $l = '';
  while ($n > 0) {
    $l = chr(65 + (($n - 1) % 26)) . $l;
    $n = (int)(($n - 1) / 26);
  }
  return $l;
}
function cellRef($col, $row)
{
  return colLetter($col) . $row;
}

// sectionColors xf index map: 3,4,5... matching order of $sectionColors
$secXfIdx = [];
$xi = 3;
foreach ($sectionColors as $sec => $hex) {
  $secXfIdx[$sec] = $xi++;
};

$sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="20" customHeight="1"/>';
$sheetXml .= '<cols><col min="1" max="1" width="22" customWidth="1"/><col min="2" max="2" width="55" customWidth="1"/><col min="3" max="3" width="18" customWidth="1"/><col min="4" max="4" width="18" customWidth="1"/><col min="5" max="5" width="22" customWidth="1"/></cols>';
$sheetXml .= '<sheetData>';
// Header row
$sheetXml .= '<row r="1" ht="28" customHeight="1">';
foreach ($headers as $ci => $h) {
  $ref = cellRef($ci + 1, 1);
  $si = addStr($h);
  $sheetXml .= '<c r="' . $ref . '" t="s" s="1"><v>' . $si . '</v></c>';
}
$sheetXml .= '</row>';
// Data rows
foreach ($dataRows as $ri => $row) {
  $rowNum = $ri + 2;
  $sec = $row[2] ?? '';
  $xfStyle = isset($secXfIdx[$sec]) ? $secXfIdx[$sec] : 2;
  $sheetXml .= '<row r="' . $rowNum . '" ht="18" customHeight="1">';
  foreach ($row as $ci => $cell) {
    $ref = cellRef($ci + 1, $rowNum);
    $si = addStr((string)$cell);
    $sheetXml .= '<c r="' . $ref . '" t="s" s="' . $xfStyle . '"><v>' . $si . '</v></c>';
  }
  $sheetXml .= '</row>';
}
$sheetXml .= '</sheetData>';
$sheetXml .= '<autoFilter ref="A1:E1"/></worksheet>';

// workbook
$wbXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Activity Logs" sheetId="1" r:id="rId1"/></sheets></workbook>';
// workbook rels
$wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
// package rels
$pkgRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
// content types
$ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';

$tmpFile = tempnam(sys_get_temp_dir(), 'lms_xlsx_');
$zip = new ZipArchive();
$zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('[Content_Types].xml', $ct);
$zip->addFromString('_rels/.rels', $pkgRels);
$zip->addFromString('xl/workbook.xml', $wbXml);
$zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
$zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
$zip->addFromString('xl/sharedStrings.xml', $ssXml);
$zip->addFromString('xl/styles.xml', $styleXml);
$zip->close();

$fname = 'activity-logs-' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-cache');
readfile($tmpFile);
@unlink($tmpFile);
exit;
