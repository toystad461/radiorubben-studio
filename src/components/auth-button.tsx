"use client";
import { signIn, signOut } from "next-auth/react";
export function AuthButton({ signedIn = false }: { signedIn?: boolean }) {
  return (
    <button
      className={signedIn ? "button secondary" : "button"}
      onClick={() =>
        signedIn
          ? signOut({ callbackUrl: "/login" })
          : signIn("azure-ad", { callbackUrl: "/" })
      }
    >
      {signedIn ? "Logg ut" : "Logg inn med Microsoft"}
    </button>
  );
}
