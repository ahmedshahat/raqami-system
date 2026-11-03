<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ConstructionVisualIdentityTest extends TestCase
{
    public function test_construction_workflows_use_the_shared_blue_teal_identity(): void
    {
        $root = dirname(__DIR__, 2);
        $dashboard = file_get_contents($root . '/public/modules/construction/css/dashboard.css');
        $refinement = file_get_contents($root . '/public/modules/construction/css/refinement.css');
        $raqmi = file_get_contents($root . '/public/css/raqmi.css');
        $quotePrint = file_get_contents($root . '/Modules/Construction/Resources/views/quotes/print.blade.php');

        $this->assertStringContainsString('--ct-brand-900: #102f63;', $dashboard);
        $this->assertStringContainsString('--ct-brand-800: #145da0;', $dashboard);
        $this->assertStringContainsString('--ct-brand-600: #0f8098;', $dashboard);

        $this->assertStringContainsString('--ct-list-primary: var(--ct-brand-800, #145da0);', $refinement);
        $this->assertStringContainsString('.ct-list-hero--contracts,', $refinement);
        $this->assertStringContainsString('.ct-contract-details .ct-contract-detail-head,', $refinement);

        // شاشات عروض الأسعار على نظام التصميم المشترك (raqmi.css) بدل التنسيقات الخاصة القديمة
        $this->assertStringContainsString('--rr-brand: #22177A;', $raqmi);
        $this->assertFileDoesNotExist($root . '/Modules/Construction/Resources/views/quotes/styles.blade.php');
        foreach (['index', 'show', 'import_review'] as $view) {
            $markup = file_get_contents($root . '/Modules/Construction/Resources/views/quotes/' . $view . '.blade.php');
            $this->assertStringContainsString('class="content rr-page"', $markup);
            $this->assertStringNotContainsString('quotes.styles', $markup);
        }

        $this->assertStringContainsString('.accent{height:5px;background:#145da0', $quotePrint);
        $this->assertStringContainsString('.items th{background:#145da0', $quotePrint);
    }
}
