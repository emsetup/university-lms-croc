<?php

namespace App\Http\Controllers;

use App\Models\FinalLabResult;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View as ViewContract;
use Illuminate\View\View;

class AdminPanelController extends Controller
{
    public function show(Request $request): View
    {
        return view('admin.panel', [
            'adminKey' => (string) $request->query('key', ''),
        ]);
    }

    public function certificates(Request $request): ViewContract
    {
        $adminKey = (string) $request->query('key', '');

        $items = FinalLabResult::query()
            ->with('learner:id,email')
            ->whereNotNull('certificate_full_name')
            ->whereNotNull('certificate_serial')
            ->orderByDesc('certificate_issued_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        foreach ($items as $row) {
            if ($row->certificate_issued_at === null) {
                $resolved = $this->resolveIssuedAtFromSerial((string) $row->certificate_serial);
                if ($resolved !== null) {
                    $row->certificate_issued_at = $resolved;
                    $row->save();
                }
            }
        }

        return view('admin.certificates', [
            'adminKey' => $adminKey,
            'items' => $items,
        ]);
    }

    public function certificateShow(Request $request, FinalLabResult $result): ViewContract
    {
        $adminKey = (string) $request->query('key', '');
        $result->loadMissing('learner:id,email');

        return view('admin.certificate-preview', [
            'adminKey' => $adminKey,
            'row' => $result,
        ]);
    }

    private function resolveIssuedAtFromSerial(string $serial): ?Carbon
    {
        if (preg_match('/CROC-ALT-(\d{8})-/', $serial, $m) !== 1) {
            return null;
        }
        if (! Carbon::hasFormat($m[1], 'Ymd')) {
            return null;
        }
        $date = Carbon::createFromFormat('Ymd', $m[1]);

        return $date->startOfDay();
    }
}
