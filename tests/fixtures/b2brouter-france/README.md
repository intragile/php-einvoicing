# B2Brouter French flow test fixtures

[intragile.eu](https://intragile.eu)

Hand-written JSON bodies that follow the shapes of the B2Brouter API `2026-06-26` French flow answers: the `ExternalCompany` object, flat as the API returns it and wrapped in `external_company` as the French guide shows it, the `processing` answer, the invoice import answers (`InvoiceResponse` in a created and in an error state) and the general `error` object, where the `invoice_id` of the 422 answer is an assumption: B2Brouter documents no field that names the invoice a refused import left behind. Names and identifiers are fictitious; the two short status messages are re-typed from sandbox answers, no documentation text is copied, and no `request_log_url` points at a real request.

---

[intragile.eu](https://intragile.eu)
