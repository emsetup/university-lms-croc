<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Learner;
use App\Services\PortalBugReportService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Inbox багов / предложений: только указанные email (по умолчанию emednikov@croc.ru). */
final class EnsureBugReportsInbox
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = (int) session('learner_id', 0);
        $learner = $id > 0 ? Learner::query()->find($id) : null;
        abort_unless(PortalBugReportService::learnerCanAccessInbox($learner), 404);

        return $next($request);
    }
}
