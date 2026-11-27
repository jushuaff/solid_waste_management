<?php
namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class ReportPdf
{
    /**
     * Render a real PDF with private, no-store headers.
     * download=false: inline PDF browser preview; download=true: file download.
     */
    public function respond(string $view, array $data, string $filename, bool $download = false, string $orientation = 'portrait')
    {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper('a4', $orientation)
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'DejaVu Sans');

        $response = $download ? $pdf->download($filename) : $pdf->stream($filename);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
