import { test } from "node:test";
import assert from "node:assert/strict";
import { isAuthConfigured } from "../src/lib/auth/config";
const configured = {
  NODE_ENV: "development",
  NEXTAUTH_URL: "http://localhost:3000",
  NEXTAUTH_SECRET: "test-only-value-not-a-real-secret-00000000",
  AZURE_AD_TENANT_ID: "00000000-0000-0000-0000-000000000001",
  AZURE_AD_CLIENT_ID: "00000000-0000-0000-0000-000000000002",
  AZURE_AD_CLIENT_SECRET: "test-only-client-value",
};
test("empty or partial configuration never enables authentication", () => {
  assert.equal(isAuthConfigured({}), false);
  for (const key of Object.keys(configured).filter((k) => k !== "NODE_ENV")) {
    assert.equal(isAuthConfigured({ ...configured, [key]: "" }), false, key);
  }
});
test("requires a specific organization and sufficiently long session secret", () => {
  for (const tenant of ["common", "organizations", "consumers", "replace-me"])
    assert.equal(
      isAuthConfigured({ ...configured, AZURE_AD_TENANT_ID: tenant }),
      false,
    );
  assert.equal(
    isAuthConfigured({ ...configured, NEXTAUTH_SECRET: "short" }),
    false,
  );
});
test("local development and HTTPS production are allowed", () => {
  assert.equal(isAuthConfigured(configured), true);
  assert.equal(
    isAuthConfigured({ ...configured, NODE_ENV: "production" }),
    false,
  );
  assert.equal(
    isAuthConfigured({
      ...configured,
      NODE_ENV: "production",
      NEXTAUTH_URL: "https://studio.radiorubben.no",
    }),
    true,
  );
  assert.equal(
    isAuthConfigured({ ...configured, NEXTAUTH_URL: "http://example.com" }),
    false,
  );
});
