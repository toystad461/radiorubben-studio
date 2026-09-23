import { spawn } from "node:child_process";
import assert from "node:assert/strict";
const port = 3107;
const origin = `http://127.0.0.1:${port}`;
async function verify(configured) {
  const server = spawn(
    process.execPath,
    [
      "node_modules/next/dist/bin/next",
      "start",
      "--hostname",
      "127.0.0.1",
      "--port",
      String(port),
    ],
    {
      env: {
        ...process.env,
        NODE_ENV: "production",
        NEXTAUTH_URL: configured ? "https://studio.radiorubben.no" : "",
        NEXTAUTH_SECRET: configured
          ? "test-only-session-secret-0000000000000000"
          : "",
        AZURE_AD_CLIENT_ID: configured
          ? "00000000-0000-0000-0000-000000000002"
          : "",
        AZURE_AD_TENANT_ID: configured
          ? "00000000-0000-0000-0000-000000000001"
          : "",
        AZURE_AD_CLIENT_SECRET: configured ? "test-only-not-real" : "",
      },
      stdio: ["ignore", "pipe", "pipe"],
    },
  );
  let output = "";
  server.stdout.on("data", (chunk) => {
    output += chunk;
  });
  server.stderr.on("data", (chunk) => {
    output += chunk;
  });
  const closed = new Promise((resolve) => server.on("exit", resolve));
  try {
    let ready = false;
    for (let attempt = 0; attempt < 100; attempt++) {
      if (server.exitCode !== null) throw new Error(output);
      if (output.includes("Ready")) {
        ready = true;
        break;
      }
      await new Promise((resolve) => setTimeout(resolve, 100));
    }
    assert.ok(ready, output);
    const home = await fetch(origin, { redirect: "manual" });
    if (configured) {
      assert.equal(home.status, 307);
      assert.equal(home.headers.get("location"), "/login");
      const providers = await fetch(`${origin}/api/auth/providers`);
      assert.equal(providers.status, 200);
      assert.equal((await providers.json())["azure-ad"].type, "oauth");
      const csrf = await fetch(`${origin}/api/auth/csrf`);
      assert.equal(csrf.status, 200);
      assert.ok((await csrf.json()).csrfToken);
    } else {
      assert.equal(home.status, 200);
      assert.match(await home.text(), /Demonstrasjon/);
      for (const method of ["GET", "POST"]) {
        const auth = await fetch(`${origin}/api/auth/session`, { method });
        assert.equal(auth.status, 503);
      }
    }
    const login = await fetch(`${origin}/login?error=AccessDenied`);
    assert.equal(login.status, 200);
    assert.match(await login.text(), /Innloggingen kunne ikke/);
    console.log(
      configured
        ? "PASS: configured auth protects dashboard; provider and CSRF routes respond"
        : "PASS: credential-free demo and disabled auth routes",
    );
  } finally {
    server.kill("SIGTERM");
    await closed;
  }
}
await verify(false);
await verify(true);
