type Environment = Record<string, string | undefined>;
const uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
export function isAuthConfigured(env: Environment = process.env): boolean {
  let validUrl = false;
  try {
    const url = new URL(env.NEXTAUTH_URL ?? "");
    validUrl =
      url.protocol === "https:" ||
      (env.NODE_ENV !== "production" &&
        url.protocol === "http:" &&
        url.hostname === "localhost");
  } catch {
    /* Missing or invalid URL disables authentication. */
  }
  return (
    validUrl &&
    uuid.test(env.AZURE_AD_TENANT_ID ?? "") &&
    uuid.test(env.AZURE_AD_CLIENT_ID ?? "") &&
    Boolean(env.AZURE_AD_CLIENT_SECRET?.trim()) &&
    (env.NEXTAUTH_SECRET?.trim().length ?? 0) >= 32
  );
}
