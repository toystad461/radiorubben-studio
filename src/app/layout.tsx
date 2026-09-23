import type { Metadata } from "next";
import "./globals.css";
export const metadata: Metadata = {
  metadataBase: new URL("https://studio.radiorubben.no"),
  title: {
    default: "Radio Rubben Studio",
    template: "%s | Radio Rubben Studio",
  },
  description:
    "Arbeidsrommet for Radio Rubben. Programmer, ressurser og publisering samlet.",
  robots: { index: false, follow: false },
};
export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="nb">
      <body>{children}</body>
    </html>
  );
}
