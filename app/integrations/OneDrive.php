<?php
namespace RadioRubben\Integrations;
interface OneDrive { /** @return array<int, array{id:string,name:string,webUrl:string}> */ public function listFiles(): array; }
// Server-side contract only. No Graph access or token persistence yet.
