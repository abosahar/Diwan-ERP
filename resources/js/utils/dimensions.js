/**
 * Client mirror of App\Services\Accounting\DimensionPolicy: is a cost centre /
 * a project required on a line of this account? (The server decides.)
 * modes: { cost_center, project } each optional | required | pnl.
 */
export function dimensionRequired(modes, dimension, account) {
    if (!account) return false;
    if (dimension === 'cost_center' && account.requires_cost_center) return true;
    const mode = modes?.[dimension] || 'optional';
    if (mode === 'required') return true;
    return mode === 'pnl' && ['revenue', 'expense'].includes(account.type);
}
