import Link from "next/link";
import { getServerSession } from "next-auth";
import { redirect } from "next/navigation";
import {
  ArrowUpRight,
  AudioLines,
  Cloud,
  FileText,
  LayoutDashboard,
  Radio,
  ShieldCheck,
  SlidersHorizontal,
  ArrowRight,
} from "lucide-react";
import { authOptions } from "@/lib/auth/options";
import { isAuthConfigured } from "@/lib/auth/config";
import { AuthButton } from "@/components/auth-button";
import { integrations } from "@/lib/integrations/catalog";
export const dynamic = "force-dynamic";
export default async function Home() {
  const ready = isAuthConfigured();
  const session = ready ? await getServerSession(authOptions) : null;
  if (ready && !session) redirect("/login");
  return (
    <div className="shell">
      <aside className="sidebar">
        <Link href="/" className="brand">
          <span className="brand-icon">
            <Radio size={25} />
          </span>
          <span>
            radio rubben<small>STUDIO</small>
          </span>
        </Link>
        <div className="nav-label">ARBEIDSROM</div>
        <nav aria-label="Hovedmeny">
          <Link href="/" className="nav-item active" aria-current="page">
            <LayoutDashboard size={18} />
            Oversikt
          </Link>
          <a href="#integrasjoner" className="nav-item">
            <SlidersHorizontal size={18} />
            Integrasjoner
          </a>
          <a href="#kom-i-gang" className="nav-item">
            <ShieldCheck size={18} />
            Kom i gang
          </a>
        </nav>
        <div className="sidebar-bottom">
          <span className="station-dot" /> Et sted for gode historier.
          <p>studio.radiorubben.no</p>
        </div>
      </aside>
      <div className="workspace">
        <header className="topbar">
          <span>
            Arbeidsrom <span className="slash">/</span>{" "}
            <strong>Oversikt</strong>
          </span>
          <span className="version">
            STUDIO <b>01</b>
          </span>
        </header>
        <main id="main">
          <div className="heading-row">
            <div>
              <p className="eyebrow">RADIO RUBBEN STUDIO</p>
              <h1>God radio starter her.</h1>
              <p className="intro">
                Ditt samlingspunkt for innhold, samarbeid og gode sendinger.
              </p>
            </div>
            <span className="mode">
              <span />
              {session ? "Innlogget" : "Demonstrasjon"}
            </span>
          </div>
          <section className="hero" aria-labelledby="hero-title">
            <div className="hero-copy">
              <span className="hero-label">
                <span /> DITT DIGITALE KONTROLLROM
              </span>
              <h2 id="hero-title">
                Mer tid til radio.
                <br />
                Alt samlet på ett sted.
              </h2>
              <p>
                Fra den første ideen til historien som når ut.
                <br />
                Her bygger vi arbeidsrommet til Radio Rubben.
              </p>
              <a href="#integrasjoner" className="button light">
                Utforsk arbeidsrommet <ArrowRight size={17} />
              </a>
            </div>
            <div className="radio-art" aria-hidden="true">
              <div className="orbit orbit-one" />
              <div className="orbit orbit-two" />
              <div className="orbit orbit-three" />
              <div className="radio-center">
                <AudioLines size={88} strokeWidth={1.4} />
              </div>
              <span className="frequency">RADIO RUBBEN — NÆR DEG</span>
            </div>
          </section>
          <section id="integrasjoner" className="section">
            <div className="section-heading">
              <div>
                <p className="eyebrow">VERKTØYENE DINE</p>
                <h2>Ett studio. Flere muligheter.</h2>
              </div>
              <span className="small">Vi bygger videre, steg for steg.</span>
            </div>
            <div className="cards">
              {integrations.map((item) => (
                <article className="integration-card" key={item.id}>
                  <div className="card-top">
                    <span className={"tool-icon " + item.id}>
                      {item.id === "onedrive" ? (
                        <Cloud size={27} />
                      ) : (
                        <FileText size={25} />
                      )}
                    </span>
                    <span className="badge">Ikke tilkoblet</span>
                  </div>
                  <p className="eyebrow">{item.category}</p>
                  <h3>{item.title}</h3>
                  <p>{item.description}</p>
                  <details>
                    <summary>
                      Se hva som kommer <ArrowUpRight size={16} />
                    </summary>
                    <p>
                      {item.planned}. Integrasjonen er planlagt og henter ingen
                      data ennå.
                    </p>
                  </details>
                </article>
              ))}
              <article className="integration-card">
                <div className="card-top">
                  <span className="tool-icon microsoft">
                    <ShieldCheck size={26} />
                  </span>
                  <span className="badge">
                    {ready ? "Konfigurert" : "Klargjort"}
                  </span>
                </div>
                <p className="eyebrow">TILGANG OG SAMARBEID</p>
                <h3>Microsoft 365</h3>
                <p>
                  {session
                    ? `Du er innlogget som ${session.user?.name ?? "medarbeider"}.`
                    : "Én arbeidskonto. En felles inngang for medarbeiderne våre."}
                </p>
                <div className="card-action">
                  {session ? (
                    <AuthButton signedIn />
                  ) : (
                    <Link href="/login">
                      Om Microsoft-innlogging <ArrowUpRight size={16} />
                    </Link>
                  )}
                </div>
              </article>
            </div>
          </section>
          <section id="kom-i-gang" className="getting-started">
            <div>
              <p className="eyebrow">NESTE STEG</p>
              <h2>Et godt grunnlag er på plass.</h2>
              <p>
                Studioet er klart for videre utvikling. Slik tar vi det i bruk.
              </p>
            </div>
            <ol>
              <li>
                <span>01</span>
                <div>
                  <strong>Gi medarbeiderne tilgang</strong>
                  <p>Konfigurer Microsoft Entra ID for organisasjonen.</p>
                </div>
              </li>
              <li>
                <span>02</span>
                <div>
                  <strong>Koble til ressursene</strong>
                  <p>Bygg filbiblioteket med OneDrive og Microsoft Graph.</p>
                </div>
              </li>
              <li>
                <span>03</span>
                <div>
                  <strong>Gjør veien til publisering kortere</strong>
                  <p>Koble WordPress til studioets arbeidsflyt.</p>
                </div>
              </li>
            </ol>
          </section>
          <footer>
            <span>
              Radio Rubben <b>Studio</b>
            </span>
            <span>
              {ready
                ? "Internt arbeidsrom"
                : "Første versjon · Ingen eksterne tjenester er tilkoblet"}
            </span>
          </footer>
        </main>
      </div>
    </div>
  );
}
