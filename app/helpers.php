<?php
declare(strict_types=1);
function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $path): never { header('Location: ' . $path, true, 303); exit; }
function icon(string $name, int $size = 18): string
{
    $paths = [
        'Radio'=>'<circle cx="12" cy="12" r="2"/><path d="M5 5a10 10 0 0 0 0 14M19 5a10 10 0 0 1 0 14M8 8a6 6 0 0 0 0 8M16 8a6 6 0 0 1 0 8"/>',
        'AudioLines'=>'<path d="M3 10v4M7 6v12M11 3v18M15 7v10M19 5v14M23 10v4"/>',
        'LayoutDashboard'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'SlidersHorizontal'=>'<path d="M3 6h18M3 12h18M3 18h18M7 3v6M17 9v6M10 15v6"/>',
        'ShieldCheck'=>'<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6zM8 12l3 3 5-6"/>',
        'ArrowRight'=>'<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'ArrowUpRight'=>'<path d="M6 18 18 6M6 6h12v12"/>',
        'Cloud'=>'<path d="M6 18h12a4 4 0 0 0 0-8 6 6 0 0 0-11-3 5 5 0 0 0-1 11Z"/>',
        'FileText'=>'<path d="M5 3h10l4 4v14H5zM14 3v5h5M8 12h8M8 16h8"/>',
    ];
    return '<svg aria-hidden="true" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'.($paths[$name] ?? '').'</svg>';
}
