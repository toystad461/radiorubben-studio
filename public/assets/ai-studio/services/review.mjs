// Auto placement is only a manuscript operation; never a broadcast command.
export function canAutoPlace({enabled, requested, review, unchanged}) {
  return enabled && requested && unchanged && review?.approved === true
    && Array.isArray(review.reasons) && review.reasons.length === 0;
}
