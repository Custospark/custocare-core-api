Final architecture after these changes
After the refactor, behavior becomes:

Save flow
validate request
resolve authenticated facility/staff
normalize charge items
normalize payment methods
compute authoritative billing totals server-side
persist / upsert cycle
persist / upsert line items
deduct inventory
recalculate cycle totals from persisted line items
sync line item statuses
update visit from refreshed cycle totals

Adjustment flow
lock line item / cycle / visit
mutate line item
adjust inventory
recalculate cycle totals from persisted line items
sync line item statuses
update visit from refreshed cycle totals
That is the consistent design you want.

Most important corrections in one short list
If you only implement the highest-value fixes first, do these in order:

Stop trusting X-Staff-Id
Normalize backend billing totals from charge items
Recalculate cycle totals after every line-item write
Fix adjustment status from adjusted to pending when quantity remains
Never mark visit completed while balance exists
One honest caveat
There is still one business-model assumption in your system:

Taxes are currently cycle-level, not line-item-level
Your recalc method recomputes taxes from cycle tax rates against cycle taxable total.

That is fine if taxes are truly global for the cycle.

If later you need:

taxable items vs non-taxable items
different taxes per item
insurance-specific tax handling
then taxes must move into a more granular model.

But for your current structure, the changes above are the correct and safe path