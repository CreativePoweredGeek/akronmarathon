<?php

namespace EEHarbor\DetourPro\Service;

class AnalyticsService
{
    private $site_id;

    public function __construct($site_id = null)
    {
        if ($site_id === null) {
            $site_id = ee()->config->item('site_id');
        }

        $this->site_id = (int) $site_id;
    }

    public function set_site_id($site_id)
    {
        $this->site_id = (int) $site_id;

        return $this;
    }

    public function get_404_weekly_series($weeks = 12)
    {
        $weeks = max((int) $weeks, 1);
        $labels = array();
        $data = array();
        $buckets = array();

        $start_of_current_week = strtotime('monday this week');
        if ($start_of_current_week === false) {
            $start_of_current_week = strtotime(date('Y-m-d'));
        }

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $bucket_ts = strtotime('-' . $i . ' week', $start_of_current_week);
            $bucket_key = date('Y-m-d', $bucket_ts);
            $buckets[$bucket_key] = 0;
            $labels[] = date('M j', $bucket_ts);
        }

        $start_date = date('Y-m-d', strtotime('-' . ($weeks - 1) . ' week', $start_of_current_week));
        ee()->db->select('hit_date, hits');
        ee()->db->from('detours_not_found');
        ee()->db->where('site_id', $this->site_id);
        ee()->db->where('hit_date >=', $start_date);
        ee()->db->where('hit_date <=', date('Y-m-d'));
        $rows = ee()->db->get()->result_array();

        foreach ($rows as $row) {
            if (empty($row['hit_date'])) {
                continue;
            }

            $week_ts = strtotime('monday this week', strtotime((string) $row['hit_date']));
            if ($week_ts === false) {
                continue;
            }

            $week_key = date('Y-m-d', $week_ts);
            if (isset($buckets[$week_key])) {
                $buckets[$week_key] += (int) $row['hits'];
            }
        }

        foreach ($buckets as $count) {
            $data[] = $count;
        }

        return array(
            'labels' => $labels,
            'data' => $data,
        );
    }

    public function get_404_daily_series($days = 30)
    {
        $days = max((int) $days, 1);
        $range = $this->build_daily_range($days);
        $buckets = array_fill_keys(array_keys($range['buckets']), 0);

        ee()->db->select('hit_date');
        ee()->db->select('SUM(hits) AS total_hits', false);
        ee()->db->from('detours_not_found');
        ee()->db->where('site_id', $this->site_id);
        ee()->db->where('hit_date >=', $range['start_date']);
        ee()->db->where('hit_date <=', date('Y-m-d'));
        ee()->db->group_by('hit_date');
        $rows = ee()->db->get()->result_array();

        foreach ($rows as $row) {
            $day = $row['hit_date'];
            if (isset($buckets[$day])) {
                $buckets[$day] = (int) $row['total_hits'];
            }
        }

        return array(
            'labels' => $range['labels'],
            'data' => array_values($buckets),
        );
    }

    public function get_404_monthly_series($months = 12)
    {
        $months = max((int) $months, 1);
        $labels = array();
        $buckets = array();

        $start_of_current_month = strtotime(date('Y-m-01'));
        if ($start_of_current_month === false) {
            $start_of_current_month = strtotime(date('Y-m-d'));
        }

        for ($i = $months - 1; $i >= 0; $i--) {
            $month_ts = strtotime('-' . $i . ' month', $start_of_current_month);
            $month_key = date('Y-m', $month_ts);
            $buckets[$month_key] = 0;
            $labels[] = date('M Y', $month_ts);
        }

        $start_date = date('Y-m-01', strtotime('-' . ($months - 1) . ' month', $start_of_current_month));
        ee()->db->select('DATE_FORMAT(hit_date, "%Y-%m") AS hit_month', false);
        ee()->db->select('SUM(hits) AS total_hits', false);
        ee()->db->from('detours_not_found');
        ee()->db->where('site_id', $this->site_id);
        ee()->db->where('hit_date >=', $start_date);
        ee()->db->where('hit_date <=', date('Y-m-d'));
        ee()->db->group_by('hit_month');
        $rows = ee()->db->get()->result_array();

        foreach ($rows as $row) {
            $month = $row['hit_month'];
            if (isset($buckets[$month])) {
                $buckets[$month] = (int) $row['total_hits'];
            }
        }

        return array(
            'labels' => $labels,
            'data' => array_values($buckets),
        );
    }

    public function get_301_daily_series($days = 30)
    {
        $days = max((int) $days, 1);
        $range = $this->build_daily_range($days);
        $buckets = array_fill_keys(array_keys($range['buckets']), 0);

        ee()->db->select('DATE(dh.hit_date) AS hit_day', false);
        ee()->db->select('COUNT(*) AS total_hits', false);
        ee()->db->from('detours_hits dh');
        ee()->db->join('detours d', 'd.detour_id = dh.detour_id', 'left');
        ee()->db->where('d.site_id', $this->site_id);
        ee()->db->where('d.detour_method', 301);
        ee()->db->where('dh.hit_date >=', $range['start_datetime']);
        ee()->db->where('dh.hit_date <=', date('Y-m-d 23:59:59'));
        ee()->db->group_by('DATE(dh.hit_date)');
        $rows = ee()->db->get()->result_array();

        foreach ($rows as $row) {
            $day = $row['hit_day'];
            if (isset($buckets[$day])) {
                $buckets[$day] = (int) $row['total_hits'];
            }
        }

        return array(
            'labels' => $range['labels'],
            'data' => array_values($buckets),
        );
    }

    public function get_301_monthly_series($months = 12)
    {
        $months = max((int) $months, 1);
        $labels = array();
        $buckets = array();

        $start_of_current_month = strtotime(date('Y-m-01'));
        if ($start_of_current_month === false) {
            $start_of_current_month = strtotime(date('Y-m-d'));
        }

        for ($i = $months - 1; $i >= 0; $i--) {
            $month_ts = strtotime('-' . $i . ' month', $start_of_current_month);
            $month_key = date('Y-m', $month_ts);
            $buckets[$month_key] = 0;
            $labels[] = date('M Y', $month_ts);
        }

        $start_date = date('Y-m-01', strtotime('-' . ($months - 1) . ' month', $start_of_current_month));

        ee()->db->select('DATE_FORMAT(dh.hit_date, "%Y-%m") AS hit_month', false);
        ee()->db->select('COUNT(*) AS total_hits', false);
        ee()->db->from('detours_hits dh');
        ee()->db->join('detours d', 'd.detour_id = dh.detour_id', 'left');
        ee()->db->where('d.site_id', $this->site_id);
        ee()->db->where('d.detour_method', 301);
        ee()->db->where('dh.hit_date >=', $start_date . ' 00:00:00');
        ee()->db->where('dh.hit_date <=', date('Y-m-d 23:59:59'));
        ee()->db->group_by('hit_month');
        $rows = ee()->db->get()->result_array();

        foreach ($rows as $row) {
            $month = $row['hit_month'];
            if (isset($buckets[$month])) {
                $buckets[$month] = (int) $row['total_hits'];
            }
        }

        return array(
            'labels' => $labels,
            'data' => array_values($buckets),
        );
    }

    public function get_top_301_hit_rows($days = 30, $limit = 10)
    {
        $days = max((int) $days, 1);
        $limit = max((int) $limit, 1);
        $start_datetime = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' day'));

        ee()->db->select('d.detour_id');
        ee()->db->select('d.original_url');
        ee()->db->select('COUNT(dh.hit_id) AS total_hits', false);
        ee()->db->from('detours_hits dh');
        ee()->db->join('detours d', 'd.detour_id = dh.detour_id', 'left');
        ee()->db->where('d.site_id', $this->site_id);
        ee()->db->where('d.detour_method', 301);
        ee()->db->where('dh.hit_date >=', $start_datetime);
        ee()->db->where('dh.hit_date <=', date('Y-m-d 23:59:59'));
        ee()->db->group_by('d.detour_id');
        ee()->db->order_by('total_hits', 'desc');
        ee()->db->limit($limit);
        $rows = ee()->db->get()->result_array();

        $results = array();
        foreach ($rows as $row) {
            $results[] = array(
                'detour_id' => isset($row['detour_id']) ? (int) $row['detour_id'] : 0,
                'label' => !empty($row['original_url']) ? $row['original_url'] : '#' . $row['detour_id'],
                'hits' => (int) $row['total_hits'],
            );
        }

        return $results;
    }

    public function get_top_needed_redirect_rows($days = null, $limit = 10)
    {
        $limit = max((int) $limit, 1);

        ee()->db->select('original_url');
        ee()->db->select('SUM(hits) AS total_hits', false);
        ee()->db->from('detours_not_found');
        ee()->db->where('site_id', $this->site_id);
        ee()->db->where('hit_date <=', date('Y-m-d'));
        if ($days !== null) {
            $days = max((int) $days, 1);
            $start_date = date('Y-m-d', strtotime('-' . ($days - 1) . ' day'));
            ee()->db->where('hit_date >=', $start_date);
        }
        ee()->db->where('detour_id IS NULL', null, false);
        ee()->db->group_by('original_url');
        ee()->db->order_by('total_hits', 'desc');
        ee()->db->limit($limit);
        $rows = ee()->db->get()->result_array();

        $results = array();
        foreach ($rows as $row) {
            $results[] = array(
                'label' => $row['original_url'],
                'hits' => (int) $row['total_hits'],
            );
        }

        return $results;
    }

    public function get_redirected_vs_404_series($days = 1)
    {
        $days = max((int) $days, 1);
        $range = $this->build_daily_range($days);

        $method_301_buckets = array_fill_keys(array_keys($range['buckets']), 0);
        $not_found_buckets = array_fill_keys(array_keys($range['buckets']), 0);

        ee()->db->select('hit_date');
        ee()->db->select('SUM(hits) AS total_404', false);
        ee()->db->from('detours_not_found');
        ee()->db->where('site_id', $this->site_id);
        ee()->db->where('hit_date >=', $range['start_date']);
        ee()->db->where('hit_date <=', date('Y-m-d'));
        ee()->db->group_by('hit_date');
        $not_found_rows = ee()->db->get()->result_array();

        foreach ($not_found_rows as $row) {
            $day = $row['hit_date'];
            if (isset($not_found_buckets[$day])) {
                $not_found_buckets[$day] = 0 - (int) $row['total_404'];
            }
        }

        ee()->db->select('DATE(dh.hit_date) AS hit_day', false);
        ee()->db->select('COUNT(*) AS total_301', false);
        ee()->db->from('detours_hits dh');
        ee()->db->join('detours d', 'd.detour_id = dh.detour_id', 'left');
        ee()->db->where('d.site_id', $this->site_id);
        ee()->db->where('d.detour_method', 301);
        ee()->db->where('dh.hit_date >=', $range['start_datetime']);
        ee()->db->where('dh.hit_date <=', date('Y-m-d 23:59:59'));
        ee()->db->group_by('DATE(dh.hit_date)');
        $method_301_rows = ee()->db->get()->result_array();

        foreach ($method_301_rows as $row) {
            $day = $row['hit_day'];
            if (isset($method_301_buckets[$day])) {
                $method_301_buckets[$day] = (int) $row['total_301'];
            }
        }

        return array(
            'labels' => $range['labels'],
            'not_found' => array_values($not_found_buckets),
            'redirected' => array_values($method_301_buckets),
        );
    }

    public function get_redirected_vs_404_monthly_series($months = 12)
    {
        $months = max((int) $months, 1);
        $labels = array();
        $month_keys = array();
        $not_found_buckets = array();
        $method_301_buckets = array();

        $start_of_current_month = strtotime(date('Y-m-01'));
        if ($start_of_current_month === false) {
            $start_of_current_month = strtotime(date('Y-m-d'));
        }

        for ($i = $months - 1; $i >= 0; $i--) {
            $month_ts = strtotime('-' . $i . ' month', $start_of_current_month);
            $month_key = date('Y-m', $month_ts);
            $month_keys[] = $month_key;
            $labels[] = date('M Y', $month_ts);
            $not_found_buckets[$month_key] = 0;
            $method_301_buckets[$month_key] = 0;
        }

        $start_date = date('Y-m-01', strtotime('-' . ($months - 1) . ' month', $start_of_current_month));

        ee()->db->select('DATE_FORMAT(hit_date, "%Y-%m") AS hit_month', false);
        ee()->db->select('SUM(hits) AS total_404', false);
        ee()->db->from('detours_not_found');
        ee()->db->where('site_id', $this->site_id);
        ee()->db->where('hit_date >=', $start_date);
        ee()->db->where('hit_date <=', date('Y-m-d'));
        ee()->db->group_by('hit_month');
        $not_found_rows = ee()->db->get()->result_array();

        foreach ($not_found_rows as $row) {
            $month = $row['hit_month'];
            if (isset($not_found_buckets[$month])) {
                $not_found_buckets[$month] = 0 - (int) $row['total_404'];
            }
        }

        ee()->db->select('DATE_FORMAT(dh.hit_date, "%Y-%m") AS hit_month', false);
        ee()->db->select('COUNT(*) AS total_301', false);
        ee()->db->from('detours_hits dh');
        ee()->db->join('detours d', 'd.detour_id = dh.detour_id', 'left');
        ee()->db->where('d.site_id', $this->site_id);
        ee()->db->where('d.detour_method', 301);
        ee()->db->where('dh.hit_date >=', $start_date . ' 00:00:00');
        ee()->db->where('dh.hit_date <=', date('Y-m-d 23:59:59'));
        ee()->db->group_by('hit_month');
        $method_301_rows = ee()->db->get()->result_array();

        foreach ($method_301_rows as $row) {
            $month = $row['hit_month'];
            if (isset($method_301_buckets[$month])) {
                $method_301_buckets[$month] = (int) $row['total_301'];
            }
        }

        $not_found = array();
        $redirected = array();
        foreach ($month_keys as $month_key) {
            $not_found[] = (int) $not_found_buckets[$month_key];
            $redirected[] = (int) $method_301_buckets[$month_key];
        }

        return array(
            'labels' => $labels,
            'not_found' => $not_found,
            'redirected' => $redirected,
        );
    }

    public function get_detour_daily_hit_series($detour_id, $days = 14)
    {
        $detour_id = (int) $detour_id;
        $days = max((int) $days, 1);
        $range = $this->build_daily_range($days);
        $buckets = array_fill_keys(array_keys($range['buckets']), 0);

        if ($detour_id <= 0) {
            return array(
                'labels' => $range['labels'],
                'data' => array_values($buckets),
            );
        }

        ee()->db->select('DATE(hit_date) AS hit_day', false);
        ee()->db->select('COUNT(*) AS total_hits', false);
        ee()->db->from('detours_hits');
        ee()->db->where('detour_id', $detour_id);
        ee()->db->where('hit_date >=', $range['start_datetime']);
        ee()->db->where('hit_date <=', date('Y-m-d 23:59:59'));
        ee()->db->group_by('DATE(hit_date)');
        $rows = ee()->db->get()->result_array();

        foreach ($rows as $row) {
            $day = $row['hit_day'];
            if (isset($buckets[$day])) {
                $buckets[$day] = (int) $row['total_hits'];
            }
        }

        return array(
            'labels' => $range['labels'],
            'data' => array_values($buckets),
        );
    }

    /**
     * Build monthly hit series data for a single detour.
     *
     * @param int|string $detour_id Detour identifier to aggregate hit rows for.
     * @param int|null $months Optional rolling month window; null returns all available months.
     * @return array{labels: array<int,string>, data: array<int,int>}
     */
    public function get_detour_monthly_hit_series($detour_id, $months = null)
    {
        $detour_id = (int) $detour_id;
        if ($detour_id <= 0) {
            return array(
                'labels' => array(),
                'data' => array(),
            );
        }

        $valid_hit_date_floor = '1000-01-01 00:00:00';
        $start_of_current_month = strtotime(date('Y-m-01'));
        if ($start_of_current_month === false) {
            $start_of_current_month = strtotime(date('Y-m-d'));
        }

        $start_month_ts = null;
        if ($months === null) {
            ee()->db->select('MIN(hit_date) AS min_hit_date', false);
            ee()->db->from('detours_hits');
            ee()->db->where('detour_id', $detour_id);
            ee()->db->where('hit_date >=', $valid_hit_date_floor);
            ee()->db->where('hit_date <=', date('Y-m-d 23:59:59'));
            $row = ee()->db->get()->row_array();
            $min_hit_date = !empty($row['min_hit_date']) ? $row['min_hit_date'] : null;

            if (empty($min_hit_date)) {
                return array(
                    'labels' => array(),
                    'data' => array(),
                );
            }

            $min_hit_ts = strtotime(substr((string) $min_hit_date, 0, 19));
            if ($min_hit_ts === false) {
                $min_hit_ts = strtotime(substr((string) $min_hit_date, 0, 10));
            }
            if ($min_hit_ts === false) {
                return array(
                    'labels' => array(),
                    'data' => array(),
                );
            }

            $start_month_ts = strtotime(date('Y-m-01', $min_hit_ts));
        } else {
            $months = max((int) $months, 1);
            $start_month_ts = strtotime('-' . ($months - 1) . ' month', $start_of_current_month);
        }

        if ($start_month_ts === false) {
            return array(
                'labels' => array(),
                'data' => array(),
            );
        }

        $month_keys = array();
        $labels = array();
        $buckets = array();
        $month_ts = $start_month_ts;
        $guard = 0;

        while ($month_ts !== false && $month_ts <= $start_of_current_month && $guard < 600) {
            $month_key = date('Y-m', $month_ts);
            $month_keys[] = $month_key;
            $labels[] = date('M Y', $month_ts);
            $buckets[$month_key] = 0;
            $month_ts = strtotime('+1 month', $month_ts);
            $guard++;
        }

        if (empty($month_keys)) {
            return array(
                'labels' => array(),
                'data' => array(),
            );
        }

        ee()->db->select('DATE_FORMAT(hit_date, "%Y-%m") AS hit_month', false);
        ee()->db->select('COUNT(*) AS total_hits', false);
        ee()->db->from('detours_hits');
        ee()->db->where('detour_id', $detour_id);
        ee()->db->where('hit_date >=', $valid_hit_date_floor);
        ee()->db->where('hit_date >=', date('Y-m-01 00:00:00', $start_month_ts));
        ee()->db->where('hit_date <=', date('Y-m-d 23:59:59'));
        ee()->db->group_by('hit_month');
        $rows = ee()->db->get()->result_array();

        foreach ($rows as $row) {
            $month = $row['hit_month'];
            if (isset($buckets[$month])) {
                $buckets[$month] = (int) $row['total_hits'];
            }
        }

        $data = array();
        foreach ($month_keys as $month_key) {
            $data[] = isset($buckets[$month_key]) ? (int) $buckets[$month_key] : 0;
        }

        return array(
            'labels' => $labels,
            'data' => $data,
        );
    }

    private function build_daily_range($days)
    {
        $labels = array();
        $buckets = array();
        $start_date = date('Y-m-d', strtotime('-' . ($days - 1) . ' day'));

        for ($i = $days - 1; $i >= 0; $i--) {
            $day_ts = strtotime('-' . $i . ' day');
            $day_key = date('Y-m-d', $day_ts);
            $buckets[$day_key] = 0;
            $labels[] = date('M j', $day_ts);
        }

        return array(
            'start_date' => $start_date,
            'start_datetime' => $start_date . ' 00:00:00',
            'labels' => $labels,
            'buckets' => $buckets,
        );
    }
}
