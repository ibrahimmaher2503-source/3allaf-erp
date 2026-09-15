<?php

declare(strict_types=1);

$mode = $argv[1] ?? '--fixture';
if (! in_array($mode, ['--fixture', '--activation'], true)) {
    throw new InvalidArgumentException('Expected --fixture or --activation.');
}

$argv[1] = $mode;
ob_start();
require dirname(__DIR__).'/v0.1.22-hotfix22/verify-navigation-runtime.php';
$navigationOutput = (string) ob_get_clean();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$assert(isset($dashboardHtml) && is_string($dashboardHtml), 'The actual dashboard layout was not rendered.');
$assert(substr_count($dashboardHtml, 'data-sidebar-scroll') === 1, 'Rendered layout must contain exactly one explicit sidebar scroller.');
$assert(substr_count($dashboardHtml, 'id="application-navigation"') === 1, 'Rendered layout must contain one application navigation id.');
$previousLibxml = libxml_use_internal_errors(true);
$document = new DOMDocument();
$assert($document->loadHTML($dashboardHtml), 'Rendered dashboard HTML could not be parsed.');
libxml_clear_errors();
libxml_use_internal_errors($previousLibxml);
$xpath = new DOMXPath($document);
$byClass = static fn (string $class): DOMNodeList => $xpath->query(
    "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]"
);
$bodyNodes = $byClass('app-layout');
$sidebarNodes = $byClass('app-sidebar');
$contentNodes = $byClass('app-layout__content');
$headerNodes = $byClass('app-topbar');
$mainNodes = $byClass('app-main');
$assert($bodyNodes->length === 1 && $sidebarNodes->length === 1 && $contentNodes->length === 1 && $headerNodes->length === 1 && $mainNodes->length === 1, 'Rendered application shell elements are incomplete or duplicated.');
$body = $bodyNodes->item(0);
$sidebar = $sidebarNodes->item(0);
$content = $contentNodes->item(0);
$header = $headerNodes->item(0);
$main = $mainNodes->item(0);
$assert($sidebar->parentNode?->isSameNode($body) === true, 'Rendered sidebar is not a direct app-shell child.');
$assert($content->parentNode?->isSameNode($body) === true, 'Rendered content is not a direct app-shell child.');
$isDescendant = static function (DOMNode $node, DOMNode $ancestor): bool {
    for ($parent = $node->parentNode; $parent !== null; $parent = $parent->parentNode) {
        if ($parent->isSameNode($ancestor)) {
            return true;
        }
    }

    return false;
};
$assert($isDescendant($header, $content) && $isDescendant($main, $content), 'Rendered header/main escaped the content column.');
$children = iterator_to_array($body->childNodes);
$assert(array_search($sidebar, $children, true) < array_search($content, $children, true), 'Rendered content does not follow the sidebar in the desktop grid.');

$manifestPath = dirname(__DIR__, 3).'/public/build/manifest.json';
$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
foreach (['resources/css/app.css', 'resources/js/app.js'] as $entry) {
    $asset = $manifest[$entry]['file'] ?? null;
    $assert(is_string($asset) && $asset !== '', "Missing Vite entry: {$entry}");
    $assert(str_contains($dashboardHtml, '/build/'.$asset), "Rendered layout omitted final Vite asset: {$asset}");
}

echo $navigationOutput;
echo 'HOTFIX23_ACTUAL_LAYOUT_RENDER=PASS mode='.ltrim($mode, '-').' html_sha256='.hash('sha256', $dashboardHtml).PHP_EOL;
echo "HOTFIX23_RENDERED_SIDEBAR_UNIQUENESS=PASS count=1\n";
echo "HOTFIX23_RENDERED_ASSET_IDENTITY=PASS\n";
