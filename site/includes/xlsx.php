<?php
/* =====================================================================
 * 极简 XLSX 写出器 —— 纯 PHP，不依赖任何第三方库。
 *
 * 为什么手写：这台机器上 PHP **没有装 zip 扩展**（/etc/php/7.4/{cli,apache2}/conf.d
 * 里没有 20-zip.ini，/usr/lib/php/20190902 下也没有 zip.so），所以 ZipArchive
 * 不可用，PhpSpreadsheet / PHPExcel 也都不在。而 .xlsx 就是一个装着若干 XML 的
 * ZIP 包 —— zlib 的 gzdeflate() 给的是裸 deflate 流，正好是 ZIP 要的格式，
 * crc32() 是内置函数，两者合起来足够自己拼一个合法的包。
 *
 * 只实现导出需要的那一小部分 OOXML：一张或多张工作表、粗体表头、冻结窗格、
 * 自动筛选、列宽、数字与文本两种单元格。没有公式、样式主题、共享字符串表
 * （文本一律 inlineStr，省掉一个部件也省掉一处出错的地方）。
 * ===================================================================== */

/**
 * 列号转 Excel 列名：0 => A、25 => Z、26 => AA。
 *
 * @param  int    $i 从 0 开始的列号
 * @return string
 */
function cnido_xlsx_col($i)
{
    $s = '';
    $i = (int)$i;
    do {
        $s = chr(65 + ($i % 26)) . $s;
        $i = intdiv($i, 26) - 1;
    } while ($i >= 0);
    return $s;
}

/**
 * XML 文本转义。
 *
 * 先剔除 XML 1.0 不允许的控制字符（\t \n \r 除外）：数据里只要混进一个，
 * 整个工作簿就打不开，而且 Excel 的报错完全指不到是哪个格子。这里不加 /u
 * 修饰符 —— 这些控制字节不可能出现在合法的 UTF-8 多字节序列里，按字节处理
 * 反而不会因为输入里有非法 UTF-8 而让 preg_replace 返回 null。
 *
 * @param  string $s
 * @return string
 */
function cnido_xlsx_esc($s)
{
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$s);
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * 拼一个 ZIP 包。
 *
 * @param  array $files  文件名 => 内容（文件名必须是 ASCII，OOXML 里都是）
 * @return string        二进制
 */
function cnido_xlsx_zip($files)
{
    $t = getdate();
    $dosTime = ($t['hours'] << 11) | ($t['minutes'] << 5) | ($t['seconds'] >> 1);
    $dosDate = (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];

    $body = '';
    $cd   = '';
    $n    = 0;
    foreach ($files as $name => $data) {
        $crc = crc32($data);
        /* 压不小就用 stored —— 小部件（_rels 之类）deflate 后常比原文还大 */
        $comp    = gzdeflate($data, 6);
        $deflate = (strlen($comp) < strlen($data));
        $blob    = $deflate ? $comp : $data;
        $method  = $deflate ? 8 : 0;
        $offset  = strlen($body);

        $body .= "PK\x03\x04"
               . pack('v', 20)                 /* version needed to extract */
               . pack('v', 0)                  /* general purpose flags */
               . pack('v', $method)
               . pack('v', $dosTime) . pack('v', $dosDate)
               . pack('V', $crc)
               . pack('V', strlen($blob))      /* compressed size */
               . pack('V', strlen($data))      /* uncompressed size */
               . pack('v', strlen($name)) . pack('v', 0)
               . $name . $blob;

        $cd .= "PK\x01\x02"
             . pack('v', 20) . pack('v', 20)   /* version made by / needed */
             . pack('v', 0)                    /* flags */
             . pack('v', $method)
             . pack('v', $dosTime) . pack('v', $dosDate)
             . pack('V', $crc)
             . pack('V', strlen($blob)) . pack('V', strlen($data))
             . pack('v', strlen($name))
             . pack('v', 0) . pack('v', 0)     /* extra len / comment len */
             . pack('v', 0) . pack('v', 0)     /* disk number / internal attrs */
             . pack('V', 32)                   /* external attrs */
             . pack('V', $offset)
             . $name;
        $n++;
    }

    $cdOffset = strlen($body);
    $cdSize   = strlen($cd);
    return $body . $cd . "PK\x05\x06"
         . pack('v', 0) . pack('v', 0)
         . pack('v', $n) . pack('v', $n)
         . pack('V', $cdSize) . pack('V', $cdOffset)
         . pack('v', 0);
}

/** styles.xml：0 = 正文，1 = 粗体（表头），2 = 数字居中。 */
function cnido_xlsx_styles()
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2">'
        . '<font><sz val="11"/><color theme="1"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="2">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '</fills>'
        . '<borders count="1"><border/></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="3">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1">'
        . '<alignment horizontal="center"/></xf>'
        . '</cellXfs>'
        . '</styleSheet>';
}

/**
 * 一张工作表。
 *
 * 元素顺序是 schema 定的，不能随手调换（sheetViews → sheetFormatPr → cols →
 * sheetData → autoFilter），顺序错了 Excel 会直接判文件损坏。
 *
 * @param  string $name    表名（不能用 : \ / ? * [ ] 且不超过 31 字符）
 * @param  array  $header  表头文本
 * @param  array  $rows    数据行；标量按类型落格（int/float 成数字，其余成文本）
 * @param  array  $o       freeze_cols / freeze_rows / filter / widths
 */
function cnido_xlsx_worksheet($name, $header, $rows, $o = array())
{
    $nCol     = count($header);
    $nRow     = count($rows) + 1;
    $lastCol  = cnido_xlsx_col(max(0, $nCol - 1));
    $freezeC  = isset($o['freeze_cols']) ? (int)$o['freeze_cols'] : 0;
    $freezeR  = isset($o['freeze_rows']) ? (int)$o['freeze_rows'] : 0;
    $widths   = isset($o['widths']) ? $o['widths'] : array();

    $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
       . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
       . '<dimension ref="A1:' . $lastCol . $nRow . '"/>'
       . '<sheetViews><sheetView workbookViewId="0">';
    if ($freezeC > 0 || $freezeR > 0) {
        $x .= '<pane'
            . ($freezeC > 0 ? ' xSplit="' . $freezeC . '"' : '')
            . ($freezeR > 0 ? ' ySplit="' . $freezeR . '"' : '')
            . ' topLeftCell="' . cnido_xlsx_col($freezeC) . ($freezeR + 1) . '"'
            . ' activePane="bottomRight" state="frozen"/>';
    }
    $x .= '</sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>';

    if ($widths) {
        $x .= '<cols>';
        foreach ($widths as $i => $w) {
            $n = $i + 1;
            $x .= '<col min="' . $n . '" max="' . $n . '" width="' . (float)$w . '" customWidth="1"/>';
        }
        $x .= '</cols>';
    }

    $x .= '<sheetData>';
    /* 表头 */
    $x .= '<row r="1">';
    foreach ($header as $i => $v) {
        $ref = cnido_xlsx_col($i) . '1';
        $x .= '<c r="' . $ref . '" s="1" t="inlineStr"><is><t xml:space="preserve">'
            . cnido_xlsx_esc($v) . '</t></is></c>';
    }
    $x .= '</row>';

    /* 数据行 */
    $r = 1;
    foreach ($rows as $row) {
        $r++;
        $x .= '<row r="' . $r . '">';
        $i = 0;
        foreach ($row as $v) {
            $ref = cnido_xlsx_col($i) . $r;
            if (is_int($v) || is_float($v)) {
                $x .= '<c r="' . $ref . '" s="2"><v>' . $v . '</v></c>';
            } elseif ($v !== null && $v !== '') {
                $x .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                    . cnido_xlsx_esc($v) . '</t></is></c>';
            } else {
                /* 空串留成真空格，而不是空文本格 —— 前者 Excel 的
                   COUNTA/ISBLANK 判断才符合直觉。 */
                $x .= '<c r="' . $ref . '" s="2"/>';
            }
            $i++;
            if ($i >= $nCol) { break; }
        }
        $x .= '</row>';
    }
    $x .= '</sheetData>';

    if (!empty($o['filter']) && $nCol > 0) {
        $x .= '<autoFilter ref="A1:' . $lastCol . $nRow . '"/>';
    }
    return $x . '</worksheet>';
}

/**
 * 打包成一个 .xlsx。
 *
 * @param  array $sheets 每项 array('name','header','rows','freeze_cols','freeze_rows','filter','widths')
 * @return string        二进制；调用方负责发 Content-Type/Content-Disposition
 */
function cnido_xlsx_build($sheets)
{
    $sheets = array_values($sheets);
    if (!$sheets) { return ''; }

    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $wbSheets = '';
    $wbRels   = '';
    foreach ($sheets as $i => $s) {
        $n = $i + 1;
        $ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml"'
             . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $wbSheets .= '<sheet name="' . cnido_xlsx_esc(cnido_xlsx_sheet_name($s['name']))
                   . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        $wbRels .= '<Relationship Id="rId' . $n . '"'
                 . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                 . ' Target="worksheets/sheet' . $n . '.xml"/>';
    }
    $ct .= '</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
          . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
          . '</Relationships>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
              . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
              . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
              . '<sheets>' . $wbSheets . '</sheets></workbook>';

    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $wbRels
            . '<Relationship Id="rId' . (count($sheets) + 1) . '"'
            . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
            . ' Target="styles.xml"/>'
            . '</Relationships>';

    $files = array(
        '[Content_Types].xml'        => $ct,
        '_rels/.rels'                => $rels,
        'xl/workbook.xml'            => $workbook,
        'xl/_rels/workbook.xml.rels' => $wbRels,
        'xl/styles.xml'              => cnido_xlsx_styles(),
    );
    foreach ($sheets as $i => $s) {
        $files['xl/worksheets/sheet' . ($i + 1) . '.xml'] = cnido_xlsx_worksheet(
            $s['name'], $s['header'], $s['rows'], $s
        );
    }
    return cnido_xlsx_zip($files);
}

/** 表名清洗：Excel 不许出现 : \ / ? * [ ]，长度上限 31。 */
function cnido_xlsx_sheet_name($n)
{
    $n = str_replace(array(':', '\\', '/', '?', '*', '[', ']'), ' ', (string)$n);
    $n = trim($n);
    if ($n === '') { $n = 'Sheet1'; }
    return mb_substr($n, 0, 31, 'UTF-8');
}

/** 发一个 xlsx 响应（清缓冲 → 头发完 → 二进制）。 */
function cnido_xlsx_send($filename, $sheets)
{
    $bin = cnido_xlsx_build($sheets);
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bin));
    header('Cache-Control: no-store');
    echo $bin;
}
