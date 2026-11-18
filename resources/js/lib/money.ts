const NANODOLLARS_PER_DOLLAR = 1_000_000_000;

export function formatNanodollars(nanodollars: number): string {
    const dollars = Math.floor(nanodollars / NANODOLLARS_PER_DOLLAR);
    const cents = String(nanodollars % NANODOLLARS_PER_DOLLAR)
        .padStart(9, '0')
        .replace(/0+$/, '')
        .padEnd(2, '0');

    return `$${dollars}.${cents}`;
}
