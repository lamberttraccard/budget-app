---
stepsCompleted: ['step-01-init', 'step-02-discovery', 'step-02b-vision', 'step-02c-executive-summary', 'step-03-success', 'step-04-journeys', 'step-05-domain', 'step-06-innovation', 'step-07-project-type', 'step-08-scoping', 'step-09-functional', 'step-10-nonfunctional', 'step-11-polish', 'step-12-complete']
workflow_completed: true
inputDocuments: ['product-brief-budget-app-2026-02-18.md']
workflowType: 'prd'
documentCounts:
  briefs: 1
  research: 0
  brainstorming: 0
  projectDocs: 0
classification:
  projectType: 'web_app'
  domain: 'fintech'
  complexity: 'medium'
  projectContext: 'greenfield'
---

# Product Requirements Document - budget-app

**Author:** Lambert
**Date:** 2026-02-19

## Executive Summary

budget-app is a personal web application that provides a clear, consolidated view of where money goes across multiple bank accounts. It solves a specific problem: every existing budget app (Finary, Bankin, etc.) buries the simple question "how much did I spend on what?" under layers of features, partner solicitations, and setup friction. budget-app answers that question in under a minute through AI-powered transaction categorization and a radically simple interface.

The app ingests CSV exports from Banque Populaire and Revolut, auto-categorizes transactions using AI that learns from user corrections, and presents a clean category overview with drill-down to individual transactions. An MCP server exposes all budget data to external AI assistants, enabling conversational financial queries through Claude. Built by a solo developer exclusively for personal use -- no monetization, no multi-user, no market to chase.

### What Makes This Special

- **Radical simplicity as a feature, not a limitation.** No onboarding, no settings screen, no dark mode, no export. The app has fewer features than any competitor and that's the point. The only way to keep a budget app simple forever is to remove the incentive to add features -- building for one user achieves that.
- **AI categorization with progressive intelligence.** 75%+ accuracy on first import, approaching 100% over time as the system learns from corrections. The effort curve trends toward zero.
- **MCP-native architecture.** The AI companion doesn't live in the app -- it lives in Claude. The app is the visual layer; the MCP server is the conversational layer. No other personal finance tool offers this.
- **Purely observational.** The app is a mirror, not a coach. It shows what happened and how it's changing. No budgets, no goals, no guilt. The user is trusted to act on the information.

## Project Classification

- **Type:** Web application (SPA, responsive, with MCP server backend)
- **Domain:** Personal finance (fintech)
- **Complexity:** Medium -- no regulatory requirements, no third-party financial APIs, no multi-user concerns. Complexity comes from AI categorization, CSV parsing across bank formats, and MCP server implementation.
- **Context:** Greenfield -- new project, no existing codebase

## Success Criteria

### User Success

- **Financial clarity achieved** -- A consolidated view of where money goes across all accounts, updated every 1-2 weeks. The "oups 500€ in restaurants" moment becomes impossible because spending is always visible.
- **Behavioral change** -- Actively adjusting spending in categories month over month. Saving more money as a direct result of awareness.
- **Sustained usage** -- The app is used consistently over months, not abandoned after initial novelty. The key risk is import friction killing the habit.
- **Effortless interaction** -- The entire loop (export CSV, import, review) takes under 2 minutes. If it feels like work, it fails.

### Business Success

N/A -- Single-user personal project. No revenue, no user growth, no market metrics. Success is purely personal utility.

### Technical Success

- **The app doesn't feel slow** -- No specific performance targets, but interactions (navigation, drill-down, import) must feel responsive and fluid.
- **AI categorization works** -- 75%+ accuracy on first import with no prior data. Near-100% for known merchants after corrections.
- **MCP server is reliable** -- Claude can query budget data without errors or timeouts.
- **Smart deduplication is silent** -- Duplicate imports produce no errors, no warnings, no user-visible friction.

### Measurable Outcomes

| Outcome | Target | Failure Signal |
|---------|--------|----------------|
| AI accuracy (first import) | 75%+ correct | Below 75% = too much manual correction |
| AI accuracy (learned) | ~100% for known merchants | Still miscategorizing after corrections |
| Import-to-overview time | < 2 minutes | Friction kills the usage habit |
| Usage consistency | Every 1-2 weeks for 6+ months | Stops importing after 2-3 months |
| MCP responsiveness | Claude gets answers without errors | Queries fail or return stale data |
| Spending awareness | Can identify categories to adjust | No behavioral change from visibility |

## User Journeys

### Journey 1: First Import -- "Where does my money actually go?"

**Opening Scene:** Lambert has just finished building budget-app. The app is running locally. He has never tracked his finances. He opens Banque Populaire's website, exports the last 3 months of transactions as a CSV. Does the same with Revolut -- two exports: personal and professional.

**Rising Action:** He opens budget-app in his browser. The main view is empty -- just a clean interface waiting for data. He drags the Banque Populaire CSV onto the import area. Transactions flow in. The AI categorizes each one: Carrefour → Food, EDF → Housing, Netflix → Entertainment. A few seconds later, categories appear with amounts. He imports Revolut personal next -- more categories fill in, numbers update. He skips the Revolut pro export for now.

**Climax:** For the first time, Lambert sees a single screen showing every euro he spent last month, organized by category. Food: 380€. Restaurants: 520€. Transport: 145€. Entertainment: 85€. The restaurants number hits him -- he had no idea it was that high.

**Resolution:** He taps Restaurants, drills into the transactions. Sees the dates, the merchants, the amounts. It's all there. He spots a few miscategorized items -- a bakery the AI filed under "Shopping." He taps, corrects to "Food." The AI notes the correction. He closes the app knowing exactly where his money went. Took about 3 minutes total.

**Requirements revealed:** CSV parsing (Banque Populaire + Revolut formats), AI categorization engine, category overview UI, drill-down navigation, manual correction interface, AI learning from corrections.

---

### Journey 2: Regular Usage -- "Bi-weekly check-in"

**Opening Scene:** It's two weeks later. Lambert exports fresh CSVs from both banks. He's done this once before, so the flow is familiar.

**Rising Action:** He imports both files. The AI categorizes everything -- this time, fewer errors because it remembers the bakery correction and similar patterns. Transactions that overlap with the previous import are silently deduplicated. The category amounts update to reflect the full month so far.

**Climax:** He glances at the overview. Food is tracking normally. Restaurants dropped -- he's been cooking more since he saw last month's number. Transport is up slightly. He taps Transport, sees a couple of Uber rides he forgot about. Nothing alarming. He navigates to last month for a quick comparison.

**Resolution:** Under a minute. He closes the app. The habit is forming -- low effort, high clarity. The AI made zero mistakes on merchants it had seen before.

**Requirements revealed:** Deduplication logic, month navigation, progressive AI accuracy, fast import processing, returning-user experience (no re-setup).

---

### Journey 3: Edge Cases -- "When things go wrong"

**Opening Scene:** Lambert imports a new CSV. The Banque Populaire format changed slightly after a website update -- a column header is different.

**Rising Action:** The import either fails with a clear message ("Unrecognized CSV format -- expected columns: date, description, amount") or partially succeeds with some fields missing. Lambert looks at the error, adjusts the CSV or maps the columns, and re-imports.

**Second scenario:** A transaction description is cryptic -- "PMT REF 4829371" -- the AI has no idea what it is and assigns a generic "Other" category. Lambert recognizes it as his gym membership, taps to correct it to "Health." Next month, the same reference auto-categorizes correctly.

**Third scenario:** Lambert accidentally imports the same file twice in a row. Nothing happens -- the app silently deduplicates. No error, no warning, no duplicate transactions.

**Resolution:** Every edge case has a recovery path that doesn't require technical knowledge. The app handles ambiguity gracefully -- worst case, something lands in "Other" and the user corrects it once.

**Requirements revealed:** CSV format validation and error messaging, "Other/Uncategorized" fallback category, robust deduplication (matching on date + amount + description), AI learning persistence, graceful error handling.

---

### Journey 4: Claude via MCP -- "Hey Claude, how's my budget?"

**Opening Scene:** Lambert is in a Claude conversation working on something else. A thought crosses his mind -- "I wonder how much I spent on eating out this quarter."

**Rising Action:** He asks Claude: "How much did I spend in the Restaurants category over the last 3 months?" Claude queries the MCP server, which reads the transaction database, filters by category and date range, sums the amounts.

**Climax:** Claude responds: "You spent 520€ in January, 380€ in February, and 290€ so far in March. That's a 44% decrease since January." Lambert didn't even open the app. The data just came to him, in conversation, with context.

**Second scenario:** Lambert asks something more complex: "What are my top 5 expense categories this month, and how do they compare to last month?" Claude queries, calculates, and presents a comparison. Lambert spots that Transport jumped 40% and asks Claude to break it down -- Claude drills into the Transport transactions via MCP and lists them.

**Resolution:** The MCP server is the bridge between raw budget data and natural conversation. Lambert gets financial insight without opening the app, without navigating, without any UI. The data meets him where he already is.

**Requirements revealed:** MCP server with read endpoints (transactions by category, by date range, by account), aggregation support (sums, counts, comparisons), transaction-level detail access, reliable response format for LLM consumption.

### Journey Requirements Summary

| Capability | J1 | J2 | J3 | J4 |
|-----------|:--:|:--:|:--:|:--:|
| CSV parsing (multi-bank) | x | x | x | |
| AI auto-categorization | x | x | x | |
| Category overview UI | x | x | | |
| 3-level drill-down | x | x | | |
| Manual correction + AI learning | x | x | x | |
| Smart deduplication | | x | x | |
| Month navigation | | x | | |
| Account separation | x | | | |
| Error handling & validation | | | x | |
| "Other" fallback category | | | x | |
| MCP read endpoints | | | | x |
| MCP aggregation & filtering | | | | x |
| MCP transaction detail access | | | | x |

## Domain-Specific Requirements

### Security Context

The app is deployed on a publicly accessible server. All endpoints (web UI and MCP) must require authentication. Financial data must never travel in plaintext. Specific security capabilities are defined in FR30-FR33 and NFR5-NFR9.

### Data Integrity

- **CSV format handling** -- European bank CSVs use commas as decimal separators, semicolons as field delimiters, and varying date formats (DD/MM/YYYY vs. YYYY-MM-DD). The parser must handle Banque Populaire and Revolut's specific formats reliably.
- **Character encoding** -- French bank exports may use ISO-8859-1 or UTF-8. The parser must handle accented characters (é, è, ê) in merchant names without corruption.
- **Categorization corrections are valuable data** -- While transaction data can be re-imported from CSVs, the learned AI corrections represent accumulated effort. Database backups are the user's responsibility (PostgreSQL pg_dump or equivalent).

### Compliance & Regulatory

- **N/A for MVP** -- No third-party financial APIs, no payment processing, no data sharing. GDPR does not apply in a meaningful way since the only data subject is the data controller (Lambert). No PCI DSS, no KYC/AML.

## Web Application Specific Requirements

### Project-Type Overview

Single-page application (SPA) with a backend API and MCP server. Mobile-first responsive design targeting Chrome on iPhone and Mac. No SEO requirements (authenticated app). No real-time data requirements (data changes only on CSV import). No accessibility compliance needed (single-user personal app).

### Technical Architecture Considerations

**Application Type:** SPA with client-side routing
- Single-page architecture supports the 3-level drill-down without page reloads
- Client-side navigation for smooth category → subcategory → transaction flow
- Backend API serves data and handles CSV processing + AI categorization
- MCP server runs alongside or integrated with the backend API

**Browser Support:**
- Chrome (Mac + iPhone) -- primary and only target
- No cross-browser testing required
- Can leverage latest CSS/JS features without polyfills

**Responsive Design:**
- Mobile-first approach -- design for iPhone screen first, scale up to Mac
- Single-column layout on mobile, potentially wider on desktop
- Touch-friendly tap targets for drill-down and category correction
- Same functionality on both form factors, no feature gating by device

### Implementation Considerations

**What to skip (not relevant):**
- Native device features (camera, GPS, push -- this is a web app)
- SEO strategy (authenticated, no public pages)
- Accessibility compliance (WCAG -- single user)
- Offline mode (requires server for data anyway)
- PWA installation (nice-to-have later, not MVP)

**What matters:**
- Clean, responsive CSS that works on Chrome mobile and desktop
- Fast navigation between views (SPA routing)
- File upload UX that works well on both mobile (file picker) and desktop (drag and drop)
- Touch and click interactions for category correction

## Project Scoping & Phased Development

### MVP Strategy & Philosophy

**MVP Approach:** Problem-Solving MVP -- deliver financial clarity as fast as possible. No market validation needed; the user is the builder. The MVP succeeds when Lambert can import CSVs and see categorized spending in under 2 minutes.

**Resource Requirements:** Solo developer. All 9 MVP features are required -- they were already scoped as the true minimum. No feature can be cut without breaking a core user journey.

### MVP Feature Set (Phase 1)

**Core User Journeys Supported:**
- Journey 1 (First Import) -- fully supported
- Journey 2 (Regular Usage) -- fully supported
- Journey 3 (Edge Cases) -- fully supported
- Journey 4 (Claude via MCP) -- fully supported

**Must-Have Capabilities:**

| # | Feature | Why It Can't Be Cut |
|---|---------|-------------------|
| 1 | CSV Import (BP + Revolut) | No data = no app |
| 2 | AI Auto-Categorization | Manual categorization kills the under-2-minute goal |
| 3 | Human Correction + AI Learning | Without correction, AI accuracy plateaus |
| 4 | Smart Deduplication | Without it, re-imports create chaos |
| 5 | Category Overview | The core screen -- the entire point of the app |
| 6 | 3-Level Drill-Down | Without detail access, the overview is unactionable |
| 7 | Monthly View | Spending is monthly; no navigation = no context |
| 8 | Account Separation | Mixing personal and professional makes data useless |
| 9 | MCP Server | A core differentiator, not a nice-to-have |

### Post-MVP Features

**Phase 2 -- Polish & Insight (Post-MVP):**
- Inline trend indicators (↓12% next to category amounts)
- Animated number transitions and smooth drill-down animations
- Satisfying import cascade animation
- Recurring transaction filter (view all subscriptions across categories)
- Search across transactions

Phase 2 is about making the app feel alive. These features build on a working MVP -- they enhance the experience but aren't needed for core financial clarity.

**Phase 3 -- Depth & Intelligence (Vision):**
- Soft spending ceilings per category
- Month-over-month evolution view
- Notifications for meaningful spending patterns
- MCP write access (Claude can correct categories or trigger actions)
- Vibrant editorial design with full delight layer

Phase 3 turns the app from a mirror into a tool that actively helps shape spending behavior. Only worth pursuing after months of sustained usage prove the concept works.

### Risk Mitigation Strategy

**Technical Risks:**

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| AI categorization accuracy below 75% | Medium | High -- too much manual work kills adoption | Start with merchant-name matching + LLM fallback; "Other" category as safety net |
| CSV format changes break import | Low | Medium -- temporary disruption | Flexible parser with clear error messages; format detection, not hardcoded parsing |
| MCP integration complexity | Low | Low -- doesn't block core app usage | Can be built after the web UI is functional; independent component |

**Resource Risks:**

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Solo developer burnout / loss of momentum | Medium | High -- project abandoned | Keep MVP tight (9 features, no more). Ship fast, iterate. Phase 2 is optional polish. |
| Scope creep during development | Medium | Medium -- delays launch | The PRD is the scope contract. If it's not in Phase 1, it doesn't exist yet. |

**Data Risks:**

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Database corruption/loss | Low | High -- lose learned AI corrections | PostgreSQL with user-managed backups (pg_dump) |
| Security breach on public server | Low | High -- financial data exposed | Authentication + HTTPS + minimal attack surface + no unnecessary ports |

## Functional Requirements

### Data Ingestion

- **FR1:** Lambert can import transaction data from Banque Populaire CSV exports
- **FR2:** Lambert can import transaction data from Revolut CSV exports (personal and professional accounts)
- **FR3:** The system can parse European CSV formats (semicolon delimiters, comma decimal separators, DD/MM/YYYY and YYYY-MM-DD date formats)
- **FR4:** The system can handle French character encoding (ISO-8859-1 and UTF-8) without corrupting merchant names
- **FR5:** The system can silently skip previously imported transactions during re-import (deduplication based on date + amount + description)
- **FR6:** Lambert can import files via drag-and-drop on desktop or file picker on mobile
- **FR7:** The system can detect unrecognized CSV formats and display clear error messages

### Transaction Intelligence

- **FR8:** The system can automatically assign categories to transactions on import
- **FR9:** The system can learn from user corrections and apply learned patterns to future imports
- **FR10:** The system can assign an "Other/Uncategorized" fallback category when confidence is low
- **FR11:** The system can categorize previously corrected merchants with near-perfect accuracy

### Financial Overview

- **FR12:** Lambert can view a category overview showing all categories with their total amounts
- **FR13:** Lambert can view income and expenses in a unified category list
- **FR14:** Lambert can drill down from categories to subcategories
- **FR15:** Lambert can drill down from subcategories to individual transactions (amount, date, merchant)
- **FR16:** Lambert can navigate between months to view spending for different time periods
- **FR17:** Lambert can view the current month's spending by default when opening the app

### Account Management

- **FR18:** Lambert can import transactions from multiple bank accounts
- **FR19:** Lambert can separate personal and professional account contexts
- **FR20:** The system excludes professional accounts from the personal view by default

### Data Correction

- **FR21:** Lambert can manually re-categorize any transaction
- **FR22:** Lambert can correct a category with a single tap/click interaction
- **FR23:** The system persists all corrections and uses them to improve future categorization

### External Access (MCP)

- **FR24:** Claude can query transactions filtered by category via MCP
- **FR25:** Claude can query transactions filtered by date range via MCP
- **FR26:** Claude can query transactions filtered by account via MCP
- **FR27:** Claude can retrieve aggregated data (sums, counts, comparisons) via MCP
- **FR28:** Claude can access individual transaction details via MCP
- **FR29:** The MCP server provides data in a format optimized for LLM consumption

### Security & Access Control

- **FR30:** Lambert can authenticate to access the application
- **FR31:** The system enforces HTTPS for all traffic
- **FR32:** The MCP endpoint requires authentication for access
- **FR33:** Unauthenticated users cannot access any financial data

## Non-Functional Requirements

### Performance

- **NFR1:** Category overview and drill-down navigation must feel instant (perceived latency under 300ms)
- **NFR2:** CSV import processing must not block the UI -- the user can see progress or continue interacting
- **NFR3:** Month navigation must render without visible loading delay
- **NFR4:** MCP queries must respond within 5 seconds for Claude to provide a fluid conversational experience

### Security

- **NFR5:** All traffic between client and server must be encrypted via HTTPS
- **NFR6:** Authentication must be required before any data is accessible (no anonymous read access)
- **NFR7:** MCP endpoint must require separate authentication -- only authorized Claude sessions can query data
- **NFR8:** Session/token management must prevent unauthorized access after authentication
- **NFR9:** The application must expose a minimal attack surface -- no unnecessary ports, endpoints, or services

### Data Integrity

- **NFR10:** The database must use PostgreSQL to support relational queries and vector extensions (pgvector) for potential semantic search on transaction data
- **NFR11:** French accented characters (é, è, ê, à, ç, etc.) must be preserved correctly through the entire pipeline (import → storage → display → MCP)
- **NFR12:** Deduplication must be deterministic -- the same import file processed twice must always produce identical results
- **NFR13:** AI correction data must persist across application restarts and updates

### Integration

- **NFR14:** The MCP server must conform to the Model Context Protocol specification for tool discovery and invocation
- **NFR15:** MCP responses must be structured for LLM consumption (clear field names, consistent formats, no HTML or UI artifacts)
