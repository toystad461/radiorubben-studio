import Link from "next/link";
import { Radio } from "lucide-react";
import { AuthButton } from "@/components/auth-button";
import { isAuthConfigured } from "@/lib/auth/config";
export const dynamic = "force-dynamic";
export default async function Login({
  searchParams,
}: {
  searchParams: Promise<{ error?: string }>;
}) {
  const ready = isAuthConfigured();
  const { error } = await searchParams;
  return (
    <main className="login-wrap">
      <section className="login-card">
        <Radio size={40} />
        <p className="eyebrow">RADIO RUBBEN / STUDIO</p>
        <h1>Velkommen inn.</h1>
        <p>Arbeidsrommet for deg som lager Radio Rubben.</p>
        {error && (
          <p role="alert" className="notice">
            Innloggingen kunne ikke fullføres. Prøv igjen eller kontakt
            administrator.
          </p>
        )}
        {ready ? (
          <AuthButton />
        ) : (
          <>
            <p className="notice">
              Microsoft-innlogging er ikke aktivert ennå. Du kan utforske
              demonstrasjonen uten konto.
            </p>
            <Link className="button" href="/">
              Åpne demonstrasjonen →
            </Link>
          </>
        )}
        <p className="small">Kun for medarbeidere i Radio Rubben.</p>
      </section>
    </main>
  );
}
