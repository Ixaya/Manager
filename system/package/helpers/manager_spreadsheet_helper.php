<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('mgr_sheet_cell')) {
	function mgr_sheet_cell($col, $row)
	{
		return "{$col}{$row}";
	}
}

if (!function_exists('mgr_sheet_range_col')) {
	function mgr_sheet_range_col($start_col, $end_col, $row = '')
	{
		return "{$start_col}{$row}:{$end_col}{$row}";
	}
}

if (!function_exists('mgr_sheet_range_row')) {
	function mgr_sheet_range_row($col, $start_row, $end_row)
	{
		return "{$col}{$start_row}:{$col}{$end_row}";
	}
}

if (!function_exists('mgr_sheet_sum_col')) {
	function mgr_sheet_sum_col(&$sheet, $col, $start_row, $end_row, $total_row)
	{
		$total_cell = mgr_sheet_cell($col, $total_row);
		$sum_range = mgr_sheet_range_row($col, $start_row, $end_row);

		$sheet->setCellValue($total_cell, "=SUM($sum_range)");
	}
}

if (!function_exists('mgr_sheet_avg_col')) {
	function mgr_sheet_avg_col(&$sheet, $col, $start_row, $end_row, $total_row, $round = true)
	{
		$total_cell = mgr_sheet_cell($col, $total_row);
		$sum_range = mgr_sheet_range_row($col, $start_row, $end_row);

		if ($round) {
			$sheet->setCellValue($total_cell, "=ROUND(AVERAGE($sum_range), 0)");
		} else {
			$sheet->setCellValue($total_cell, "=AVERAGE($sum_range)");
		}
	}
}

if (!function_exists('mgr_sheet_fill')) {
	function mgr_sheet_fill(&$sheet, $range, $color_hex)
	{
		$fill_style = $sheet->getStyle($range)->getFill();

		$fill_style->setFillType(PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
		$fill_style->getStartColor()->setARGB($color_hex);
	}
}

if (!function_exists('mgr_sheet_font')) {
	function mgr_sheet_font(&$sheet, $range, $color_hex, $size, $bold = true)
	{
		$font_style = $sheet->getStyle($range)->getFont();

		$font_style->setBold($bold);

		if (!empty($size)) {
			$font_style->setSize($size);
		}

		if (!empty($color_hex)) {
			$font_style->getColor()->setARGB($color_hex);
		}
	}
}

if (!function_exists('mgr_sheet_aligment')) {
	function mgr_sheet_aligment(&$sheet, $range, $horizontal = null, $vertical = null)
	{
		$alignment = $sheet->getStyle($range)->getAlignment();

		if (!empty($horizontal)) {
			$alignment->setHorizontal($horizontal);
		}

		if (!empty($vertical)) {
			$alignment->setVertical($vertical);
		}
	}
}

if (!function_exists('mgr_sheet_next_letter')) {
	function mgr_sheet_next_letter(&$letter)
	{
		$letter = mgr_sheet_letter_add($letter, 1);
	}
}

if (!function_exists('mgr_sheet_letter_add')) {
	function mgr_sheet_letter_add($letter, $add)
	{
		return chr(ord($letter) + $add);
		// toDo: If the letter reaches 'z', wrap around to 'aa'
	}
}
