export function receiptPaperWidth(receiptOptions) {
    return Number(receiptOptions?.paper_width) === 80 ? 80 : 58;
}
