import "server-only";
export interface ArticleDraft {
  title: string;
  content: string;
}
export interface WordPressIntegration {
  createDraft(article: ArticleDraft): Promise<{ id: number }>;
}
// No API requests or publishing enabled. See docs/integrations.md.
