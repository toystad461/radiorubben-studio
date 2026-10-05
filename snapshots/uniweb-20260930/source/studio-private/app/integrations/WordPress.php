<?php
namespace RadioRubben\Integrations;
interface WordPress { public function createDraft(string $title, string $content): int; }
// Server-side contract only. No publishing or API calls yet.
