<?php

namespace App\Core;

/**
 * Paginator — shared pagination rendering + data shaping so every list
 * screen paginates identically (server side via AJAX or full page).
 */
class Paginator
{
    /**
     * Build the standard pagination payload.
     *
     * @param int $total
     * @param int $page
     * @param int $perPage
     * @return array{total:int,page:int,per_page:int,pages:int,from:int,to:int,has_prev:bool,has_next:bool}
     */
    public static function meta($total, $page, $perPage)
    {
        $total = max(0, (int) $total);
        $perPage = max(1, (int) $perPage);
        $pages = (int) ceil($total / $perPage);
        $page = max(1, min($pages > 0 ? $pages : 1, (int) $page));

        $from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
        $to = min($total, $page * $perPage);

        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
            'from' => $from,
            'to' => $to,
            'has_prev' => $page > 1,
            'has_next' => $page < $pages,
        ];
    }

    /** Server-side LIMIT/OFFSET clause. */
    public static function limitSql($page, $perPage)
    {
        $page = max(1, (int) $page);
        $perPage = max(1, min(PER_PAGE_MAX, (int) $perPage));
        return ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage);
    }

    /**
     * Render pagination controls.
     *
     * @param array  $meta   from ::meta()
     * @param string $target data-page-target used by the JS pagination binder
     */
    public static function render(array $meta, $target = 'default')
    {
        if ($meta['pages'] <= 1) {
            return '<div class="pagination-meta">' . (int) $meta['total'] . ' record' . ($meta['total'] === 1 ? '' : 's') . '</div>';
        }

        $page = (int) $meta['page'];
        $pages = (int) $meta['pages'];

        // Window of page numbers around the current page.
        $window = [];
        $start = max(1, $page - 2);
        $end = min($pages, $start + 4);
        $start = max(1, $end - 4);
        for ($i = $start; $i <= $end; $i++) {
            $window[] = $i;
        }

        $html = '<nav class="pagination" aria-label="Pagination" data-pagination="' . e($target) . '">';
        $html .= '<span class="pagination-meta">Showing ' . (int) $meta['from'] . '–' . (int) $meta['to']
            . ' of ' . (int) $meta['total'] . '</span>';
        $html .= '<div class="pagination-controls">';

        $html .= $meta['has_prev']
            ? '<button type="button" class="page-btn" data-page="' . ($page - 1) . '" aria-label="Previous page">' . Icons::render('chevron-left', 16) . '</button>'
            : '<button type="button" class="page-btn" disabled aria-label="Previous page">' . Icons::render('chevron-left', 16) . '</button>';

        if ($start > 1) {
            $html .= '<button type="button" class="page-btn" data-page="1">1</button>';
            if ($start > 2) {
                $html .= '<span class="page-gap">…</span>';
            }
        }

        foreach ($window as $number) {
            $active = $number === $page ? ' is-active' : '';
            $html .= '<button type="button" class="page-btn' . $active . '" data-page="' . $number
                . '" aria-current="' . ($number === $page ? 'page' : 'false') . '">' . $number . '</button>';
        }

        if ($end < $pages) {
            if ($end < $pages - 1) {
                $html .= '<span class="page-gap">…</span>';
            }
            $html .= '<button type="button" class="page-btn" data-page="' . $pages . '">' . $pages . '</button>';
        }

        $html .= $meta['has_next']
            ? '<button type="button" class="page-btn" data-page="' . ($page + 1) . '" aria-label="Next page">' . Icons::render('chevron-right', 16) . '</button>'
            : '<button type="button" class="page-btn" disabled aria-label="Next page">' . Icons::render('chevron-right', 16) . '</button>';

        $html .= '</div></nav>';
        return $html;
    }
}
