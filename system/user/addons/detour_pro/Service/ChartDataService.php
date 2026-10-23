<?php

namespace EEHarbor\DetourPro\Service;

class ChartDataService
{
    private $palette = array();

    public function __construct($palette = array())
    {
        $this->set_palette($palette);
    }

    public function set_palette($palette)
    {
        $this->palette = is_array($palette) ? $palette : array();

        return $this;
    }

    public function build_404_trend_chart($dataset_label, $series)
    {
        return $this->build_line_chart($dataset_label, $series, 'not_found');
    }

    public function build_301_trend_chart($dataset_label, $series)
    {
        return $this->build_line_chart($dataset_label, $series, 'redirect_301');
    }

    public function build_top_301_chart($dataset_label, $rows)
    {
        return $this->build_ranked_bar_chart($dataset_label, $rows, 'redirect_301');
    }

    public function build_top_needed_redirects_chart($dataset_label, $rows)
    {
        return $this->build_ranked_bar_chart($dataset_label, $rows, 'not_found');
    }

    public function build_redirect_vs_404_chart($series, $not_found_label, $redirected_label, $redirect_first = false)
    {
        $labels = isset($series['labels']) ? $series['labels'] : array();
        $not_found = isset($series['not_found']) ? $series['not_found'] : array();
        $redirected = isset($series['redirected']) ? $series['redirected'] : array();

        $not_found_dataset = array(
            'label' => $not_found_label,
            'data' => $not_found,
            'backgroundColor' => $this->get_palette_value('not_found', 'bar', 'rgba(239, 68, 68, 0.92)'),
            'borderColor' => $this->get_palette_value('not_found', 'bar_border', 'rgba(185, 28, 28, 1)'),
            'borderRadius' => 6,
            'maxBarThickness' => 34,
            'borderWidth' => 1,
        );

        $redirected_dataset = array(
            'label' => $redirected_label,
            'data' => $redirected,
            'backgroundColor' => $this->get_palette_value('redirect_301', 'bar', 'rgba(34, 197, 94, 0.92)'),
            'borderColor' => $this->get_palette_value('redirect_301', 'bar_border', 'rgba(21, 128, 61, 1)'),
            'borderRadius' => 6,
            'maxBarThickness' => 34,
            'borderWidth' => 1,
        );

        $datasets = $redirect_first
            ? array($redirected_dataset, $not_found_dataset)
            : array($not_found_dataset, $redirected_dataset);

        return array(
            'labels' => $labels,
            'datasets' => $datasets,
        );
    }

    private function build_line_chart($dataset_label, $series, $palette_key)
    {
        return array(
            'labels' => isset($series['labels']) ? $series['labels'] : array(),
            'datasets' => array(
                array(
                    'label' => $dataset_label,
                    'data' => isset($series['data']) ? $series['data'] : array(),
                    'borderColor' => $this->get_palette_value($palette_key, 'line', 'rgba(148, 163, 184, 1)'),
                    'backgroundColor' => $this->get_palette_value($palette_key, 'fill', 'rgba(148, 163, 184, 0.24)'),
                    'tension' => 0.25,
                    'fill' => true,
                    'pointRadius' => 3,
                ),
            ),
        );
    }

    private function build_ranked_bar_chart($dataset_label, $rows, $palette_key)
    {
        $labels = array();
        $data = array();
        $links = array();

        foreach ((array) $rows as $row) {
            $labels[] = isset($row['label']) ? $row['label'] : '';
            $data[] = isset($row['hits']) ? (int) $row['hits'] : 0;
            $links[] = isset($row['link'])
                ? (string) $row['link']
                : (isset($row['add_detour_link']) ? (string) $row['add_detour_link'] : '');
        }

        return array(
            'labels' => $labels,
            'links' => $links,
            'datasets' => array(
                array(
                    'label' => $dataset_label,
                    'backgroundColor' => $this->get_palette_value($palette_key, 'bar', 'rgba(148, 163, 184, 0.9)'),
                    'borderColor' => $this->get_palette_value($palette_key, 'bar_border', 'rgba(100, 116, 139, 1)'),
                    'borderWidth' => 1,
                    'data' => $data,
                ),
            ),
        );
    }

    private function get_palette_value($group, $key, $fallback)
    {
        if (
            isset($this->palette[$group]) &&
            is_array($this->palette[$group]) &&
            isset($this->palette[$group][$key])
        ) {
            return $this->palette[$group][$key];
        }

        return $fallback;
    }
}
