# Gang Sheet Builder Requirements

## Purpose

This document is for gathering the requirements needed to build a new customer-facing DTF gang sheet builder and pricing tool on the Barebones Apparel website.

This feature will be separate from the existing internal quote tool.

The intended customer flow is:

1. Customer selects a gang sheet size
2. Customer uploads artwork
3. Customer arranges artwork on a gang sheet canvas
4. The site prices the sheet based on size
5. Customer submits the layout for staff approval and production

---

## Current MVP Assumption

Unless the team says otherwise, the initial version should assume:

- Customer-facing WordPress plugin
- Separate from the internal quote workflow
- Pricing based only on selected gang sheet size
- One gang sheet per submission
- Manual approval before print
- PNG uploads only for version 1
- Fixed available sheet sizes
- Drag, resize, and optionally rotate artwork on the sheet

These are starting assumptions only and should be confirmed below.

---

## Decisions Needed From Staff

### 1. Product Setup

Please define the exact gang sheet products we will sell.

- What sheet sizes do we offer?
- What is the exact printable width and height for each size?
- Are these sold as fixed sizes only, or can customers enter custom sizes?
- Will version 1 allow only one sheet per order/submission?
- Are there any sheet sizes that should be hidden from retail customers?

Example table:

| Sheet Name | Width | Height | Customer Price | Internal Cost | Active |
| --- | --- | --- | --- | --- | --- |
| 22x24 | 22 in | 24 in | $ | $ | Yes/No |
| 22x36 | 22 in | 36 in | $ | $ | Yes/No |
| 22x48 | 22 in | 48 in | $ | $ | Yes/No |

### 2. Pricing Rules

The current plan is to price by sheet size only.

Please confirm:

- Is price based only on selected sheet size?
- Will there be any quantity discounts?
- Will there be rush fees?
- Will there be setup fees?
- Will there be art cleanup fees?
- Will there be rework charges if files are bad?
- Is there a minimum order or minimum sheet size?

If pricing is fixed by size, provide the final price list to use in the builder.

### 3. Accepted Artwork Files

Please define what customers are allowed to upload.

- Which file types do we accept in version 1?
- Is PNG enough for launch, or do we need PDF, AI, SVG, PSD, or EPS?
- What maximum file size should be allowed per upload?
- Should customers be allowed to upload multiple files to one sheet?
- Must files have transparent backgrounds?
- Do we accept JPEG files at all?
- Do we require a specific color mode?

Recommended default for MVP:

- PNG only
- Transparent background preferred or required
- Max 25 MB per file

### 4. Artwork Quality Standards

Please define the minimum quality standards before production.

- Minimum DPI or resolution required?
- Minimum printable design size?
- Smallest readable text size you recommend?
- Are there design types that commonly fail in DTF?
- Do we reject low-resolution files automatically, or warn and allow submission?
- Are semi-transparent effects allowed?
- Are fades, gradients, glows, shadows, and distressed textures allowed?
- Are there limits on thin lines or very small details?

### 5. Layout Rules On The Sheet

Please define the rules the builder must enforce or warn about.

- Minimum gap required between designs?
- Minimum margin required from the sheet edge?
- Do we need a bleed or safe area?
- Can artwork be rotated?
- Can artwork be flipped horizontally or vertically?
- Should overlapping artwork be blocked entirely?
- Should designs be allowed to touch each other?
- Should the builder snap to grid, guides, or spacing rules?

Recommended default for MVP:

- 0.25 inch minimum gap between designs
- 0.25 inch minimum margin from outer edge
- Rotation allowed
- Overlap not allowed

### 6. Production Constraints

Please identify any technical limits we should enforce in the interface.

- What is the maximum printable area on each sheet?
- Are there known dead zones or non-printable margins?
- Are there common placement mistakes customers make?
- Are there art combinations that look acceptable on screen but fail in production?
- Are there size ranges that are too small to print reliably?

### 7. Approval Workflow

The current plan is for the customer to submit a sheet for approval before printing.

Please confirm:

- Does every submission require staff review?
- Can staff adjust layout without customer approval?
- If staff edits the layout, does the customer need to re-approve it?
- What statuses should exist?
- Who on the team reviews submissions?
- What information does the reviewer need to see?

Recommended status flow:

- Submitted
- Reviewing
- Needs Changes
- Approved
- Rejected
- In Production
- Completed

### 8. Customer Communication

Please define what the customer should experience after submission.

- Should the customer pay before approval or after approval?
- Should the customer receive an email confirmation immediately?
- Should they receive a separate approval or revision email later?
- Should customers be able to see order or approval status online?
- What should the message say if their artwork is low quality?
- What should the message say if the design is adjusted by staff?

### 9. Common Failure Cases

Please list the most common reasons gang sheet jobs get delayed, fixed, or rejected.

Examples:

- low-resolution art
- white background instead of transparent background
- designs too close together
- art too close to sheet edge
- tiny text
- thin lines
- customer uploads screenshots instead of source art

We should turn these into either:

- hard validation rules
- warning messages
- staff review checklist items

### 10. Staff Review Screen Requirements

Please define what the internal team needs after a customer submits.

- What customer info should staff see?
- Should staff see the original uploaded files?
- Should staff see a flattened preview of the final sheet?
- Should staff be able to edit placements?
- Should staff be able to replace uploaded files?
- Should staff be able to add internal notes?
- Should staff be able to send revision requests back to the customer?

---

## Questions To Send To Workers And Artists

Use this section as the direct questionnaire.

### Product And Pricing

1. What gang sheet sizes do we want to sell at launch?
2. What price should each sheet size be?
3. Do we want fixed sizes only, or any custom-size option?
4. Are there rush fees, setup fees, or art cleanup fees?
5. Do we want one sheet per submission, or multiple sheets in one order?

### Artwork Uploads

6. What file types should customers be allowed to upload for version 1?
7. What max file size should we allow per file?
8. Do uploaded files need transparent backgrounds?
9. Should JPEG files be rejected, warned, or allowed?
10. Should customers be able to upload multiple art files onto one sheet?

### Artwork Quality

11. What minimum resolution or DPI is acceptable?
12. What is the smallest design size we should allow?
13. What is the smallest text size that still prints well?
14. What file problems should trigger an automatic warning?
15. What file problems should cause the art to be rejected?

### Layout And Spacing

16. What minimum space is required between designs?
17. What minimum space is required from the edge of the sheet?
18. Should rotation be allowed?
19. Should flip/mirror be allowed?
20. Should overlapping artwork be prevented completely?

### Review And Production

21. Does every gang sheet require manual approval before print?
22. Can staff fix minor problems without asking the customer?
23. If staff changes a layout, does the customer need to approve it again?
24. What statuses should we use from submission through completion?
25. What are the top reasons a submitted gang sheet gets delayed or rejected?

### Customer Experience

26. Should the customer pay before approval or only after approval?
27. What confirmation should the customer receive immediately after submitting?
28. Should customers be able to track status online?
29. What warnings should we show before submission?
30. What should happen when artwork quality is questionable but still printable?

---

## Suggested Launch Defaults

If the team does not answer every question immediately, these defaults are reasonable for the first build:

- Fixed sheet sizes only
- Pricing based only on sheet size
- One sheet per submission
- PNG uploads only
- Multiple PNGs allowed on one sheet
- 25 MB upload limit per file
- Transparent background required
- 0.25 inch spacing between designs
- 0.25 inch margin from sheet edge
- Rotation allowed
- Overlap blocked
- Manual approval required before print
- Payment collected after approval

---

## Final Deliverables Needed Before Development Starts

Before implementation begins, we should have:

- Approved sheet size list
- Final pricing table
- Allowed file type list
- Upload size limit
- Layout spacing rules
- Margin or safe-zone rules
- Approval workflow and statuses
- Decision on when payment is collected
- List of top rejection/warning cases

Once these are confirmed, the next step is a technical implementation spec for the standalone plugin and admin workflow.
