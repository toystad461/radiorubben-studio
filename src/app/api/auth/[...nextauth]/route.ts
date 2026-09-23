import NextAuth from "next-auth";
import type { NextRequest } from "next/server";
import { authOptions } from "@/lib/auth/options";
import { isAuthConfigured } from "@/lib/auth/config";
const handler = NextAuth(authOptions);
async function auth(
  request: NextRequest,
  context: { params: Promise<{ nextauth: string[] }> },
) {
  if (!isAuthConfigured())
    return Response.json(
      { error: "Microsoft-innlogging er ikke konfigurert." },
      { status: 503 },
    );
  return handler(request, { params: await context.params });
}
export { auth as GET, auth as POST };
