<?php

namespace App\Http\Controllers;

use App\Services\DocumentationCatalog;
use App\Services\PortalStaffAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DocumentationController extends Controller
{
    public function __construct(private DocumentationCatalog $catalog) {}

    public function index(Request $request): View
    {
        $psa = $this->staffAccess($request);
        $grouped = $this->catalog->groupedVisible($psa);
        $first = $this->firstArticleSlug($grouped);

        return view('docs.index', [
            'grouped' => $grouped,
            'firstSlug' => $first,
            'pageTitle' => (string) config('documentation.title', 'Документация'),
            'pageSubtitle' => (string) config('documentation.subtitle', ''),
            'sectionIntro' => config('documentation.section_intro', []),
            'docsSearchIndex' => $this->catalog->searchIndex($psa),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $psa = $this->staffAccess($request);
        $article = $this->catalog->findVisibleBySlug($slug, $psa);
        abort_if($article === null, 404);

        $html = $this->catalog->addHeadingIds($this->catalog->renderBodyHtml((string) $article['body']));
        $headings = $this->catalog->extractHeadings($html);
        $nav = $this->catalog->neighbors((string) $article['slug'], $psa);

        return view('docs.show', [
            'article' => $article,
            'html' => $html,
            'headings' => $headings,
            'grouped' => $this->catalog->groupedVisible($psa),
            'pageTitle' => (string) $article['title'],
            'navPrev' => $nav['prev'],
            'navNext' => $nav['next'],
            'sectionIntro' => config('documentation.section_intro.'.((string) $article['section']), ''),
            'docsSearchIndex' => $this->catalog->searchIndex($psa),
        ]);
    }

    private function staffAccess(Request $request): ?PortalStaffAccess
    {
        $id = (int) session('learner_id', 0);
        if ($id <= 0) {
            return null;
        }

        return PortalStaffAccess::fromLearnerId($id);
    }

    /** @param array<string, list<array<string, mixed>>> $grouped */
    private function firstArticleSlug(array $grouped): ?string
    {
        foreach ($grouped as $articles) {
            if ($articles !== []) {
                return (string) $articles[0]['slug'];
            }
        }

        return null;
    }
}
