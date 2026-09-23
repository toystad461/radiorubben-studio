import "server-only";
/** Planned server-side boundary. Obtain delegated Graph tokens only after authorization. */
export interface StudioFile {
  id: string;
  name: string;
  webUrl: string;
}
export interface OneDriveIntegration {
  listFiles(): Promise<StudioFile[]>;
}
// No client or token storage yet. See docs/integrations.md.
