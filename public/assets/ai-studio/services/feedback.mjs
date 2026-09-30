export function cleanFeedback(value) {
  if (!Array.isArray(value)) return [];
  return value.filter(row => row && typeof row.reason === 'string' && typeof row.excerpt === 'string')
    .slice(-3).map(row => ({reason:row.reason.slice(0,180), excerpt:row.excerpt.slice(0,220)}));
}
export function rememberRejection(rows, text, reason) {
  return cleanFeedback([...cleanFeedback(rows), {reason:reason.trim() || 'Avvist: prøv en annen formulering og åpning.', excerpt:text}]);
}
export function feedbackPrompt(rows) {
  return cleanFeedback(rows).map((row,i)=>`${i+1}. Tilbakemelding: ${row.reason}\nAvvist utdrag (ikke fakta): ${row.excerpt}`).join('\n\n');
}
