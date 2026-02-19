---
stepsCompleted: [1, 2, 3, 4, 5, 6]
workflow_completed: true
inputDocuments: ['brainstorming-session-2026-02-18.md']
date: 2026-02-18
author: Lambert
---

# Product Brief: budget-app

## Executive Summary

budget-app is a personal web app built to scratch one itch: seeing where your money goes, simply and beautifully. Born from frustration with overcomplicated finance apps like Finary and Bankin -- which try to do everything, spam you with partner solicitations, and bury the basics under feature bloat -- budget-app takes the opposite approach. It's a financial mirror: import your bank transactions, and the app shows you a clean, categorized overview of your spending and income. AI handles categorization. You handle the decisions. As a web app, it works seamlessly across phone, Mac, and any browser. An exposed MCP server lets you plug your budget data directly into your Claude assistant for conversational financial insight. No in-app assistant needed -- the AI companion lives wherever your AI already lives. Built by one developer, for one user, with zero pressure to monetize or please a market.

---

## Core Vision

### Problem Statement

There is no simple budget overview app. Every personal finance app on the market tries to be everything -- investment tracker, savings coach, loan optimizer, partner marketplace. For someone who just wants to see where their money goes each month, these apps are exhausting. The setup is tedious, the navigation is cluttered, and the core question -- "how much did I spend on what?" -- is buried under features nobody asked for.

### Problem Impact

Without a clear picture of spending, money leaks go unnoticed. You spend 500€ at restaurants without realizing it until the bank statement shocks you. Recurring subscriptions pile up invisibly. The irony: the tools that should provide clarity actually create more noise. Users abandon budget apps not because they don't care about their finances, but because the apps demand too much effort for too little insight.

### Why Existing Solutions Fall Short

- **Finary, Bankin, and similar apps** try to be comprehensive financial platforms -- investments, savings, loans, insurance -- when most users just need a spending overview
- **Partner solicitations and monetization** clutter the experience with noise that serves the business, not the user
- **Complex onboarding and setup** create a wall before users ever see value -- manual categorization, account linking wizards, preference configurations
- **No app respects simplicity as a feature** -- they all assume more features = more value, when the opposite is true for this use case

### Proposed Solution

A single-purpose web app that answers one question: "Where does my money go?" Import a bank CSV, and the app auto-categorizes every transaction using AI. The main view is a clean list of categories with amounts and inline trend indicators. Drill down into any category to see subcategories and individual transactions. Income and expenses live on the same view. The AI learns from your corrections and gets smarter over time. An MCP server exposes budget data to external AI assistants -- plug it into Claude and ask questions about your finances conversationally. No in-app assistant; the AI companion lives in your existing tools. Every interaction is animated, smooth, and satisfying. No settings screen. No onboarding wizard. One opinionated, beautiful, responsive design that works on any device.

### Key Differentiators

- **Radical simplicity** -- the app does one thing and kills every feature that doesn't serve it. No settings, no onboarding, no export, no multi-currency
- **MCP-powered AI integration** -- expose your budget data as an MCP server and plug it into Claude or any compatible AI assistant. Your financial companion lives wherever your AI lives
- **Progressive intelligence** -- AI categorization that learns from your corrections and improves over time, reducing effort to near-zero
- **Built for one** -- no market pressure, no monetization, no feature creep. The app stays pure because it only needs to serve its creator
- **Delight in the mundane** -- animated number transitions, smooth drill-downs, satisfying import cascades that turn boring financial data into something you enjoy looking at
- **Universal access** -- web app works on phone, Mac, any browser. No app store, no installs, no platform lock-in

## Target Users

### Primary User

**Lambert -- Solo developer, building for himself**

- **Context:** Has two banks -- Banque Populaire (personal) and Revolut (personal + professional accounts). Currently tracks nothing. Doesn't check accounts regularly. This app is the starting point, not a replacement for an existing habit.
- **Current workaround:** None. No spreadsheet, no app, no tracking. Just a vague sense of where money goes, with occasional surprises.
- **Problem experience:** Money flows through multiple accounts across two banks with no consolidated view. Personal and professional expenses on Revolut are mixed in the same mental space. No visibility into spending patterns, recurring costs, or category trends.
- **Motivation:** Wants financial clarity without effort. The "oups, 500€ in restaurants" moment -- seeing the truth and being able to act on it. Not motivated by budgeting discipline, but by awareness.
- **Success vision:** Opens the app every couple of weeks after importing bank data. Instantly sees where money went. Spots patterns. Makes adjustments when something looks off. The whole interaction takes under a minute.

### Secondary Users

N/A -- This is a single-user app built exclusively for personal use. No sharing, no multi-user, no collaboration features.

### User Journey

**Discovery & Setup:**
- Lambert builds the app himself. No onboarding needed -- he knows how it works because he made it.
- First import: downloads CSV exports from Banque Populaire and Revolut, imports them. AI categorizes everything. He corrects a few misclassifications.

**Core Usage Loop:**
- Every 1-2 weeks: export transactions from both banks, import into the app
- Glance at the category overview -- amounts, trends, income vs. expenses
- Drill down if something catches his eye ("restaurants feel high this month")
- Occasionally correct a miscategorized transaction -- the AI learns

**Account Structure:**
- Banque Populaire: personal
- Revolut Personal: personal
- Revolut Professional: separate, excluded from personal budget view or shown as a distinct context

**MCP Integration:**
- Claude assistant has full read access to transaction data via MCP server
- Simple queries: "How much did I spend on food this month?"
- Complex analysis: "Compare my spending this quarter vs. last quarter" or "What are my top 3 growing expense categories?"
- The MCP server becomes the conversational layer -- the app is the visual layer

**Success Moment:**
- First month with complete data imported. Lambert sees the full picture for the first time. Realizes exactly where his money goes. The clarity alone changes behavior -- no coaching needed.

## Success Metrics

### Personal Success Criteria

- **Financial clarity achieved** -- Lambert has a clear, consolidated view of where money goes across all accounts every month. The "oups 500€ in restaurants" moment becomes impossible because spending is always visible.
- **Behavioral change** -- Actively adjusting spending in categories month over month based on what the app reveals. Saving more money as a direct result of awareness.
- **Sustained usage** -- The app is used consistently (every 1-2 weeks) over months, not abandoned after initial novelty. The key risk is import friction killing the habit.

### Business Objectives

N/A -- This is a personal project with no business model, no users to acquire, and no revenue goals. Success is purely personal utility.

### Key Performance Indicators

| KPI | Target | Measurement |
|-----|--------|-------------|
| AI categorization accuracy (first import) | 75%+ | Correct categories vs. total transactions on initial import with no prior data |
| AI categorization accuracy (learned) | ~100% | Correct categories for previously seen merchants after corrections |
| Time from CSV export to full overview | < 2 minutes | Export from bank + import + review. If this exceeds 2 min, friction kills the habit |
| Import frequency | Every 1-2 weeks | Consistent usage indicates the app is providing value and the friction is manageable |
| Category spending trend | Measurable month-over-month changes | Lambert can point to specific categories where he reduced spending based on app visibility |
| App longevity | Still in active use after 6 months | The ultimate test -- did he build something he actually keeps using? |

### Failure Indicators

- **Stops importing after 2-3 months** -- import friction was too high or the insight wasn't valuable enough
- **AI accuracy stays below 75%** -- manual correction burden makes the app feel like work
- **No behavioral change** -- sees the data but doesn't act on it (though this may be a user problem, not an app problem)
- **Never opens the app on mobile** -- the responsive design failed and it's desktop-only in practice

## MVP Scope

### Core Features

1. **CSV Import** -- Upload transaction exports from Banque Populaire and Revolut. The app parses and ingests them. Support for multiple file formats as needed per bank.
2. **AI Auto-Categorization** -- Every transaction is automatically assigned a category on import. 75%+ accuracy on first import with no prior data.
3. **Human Correction** -- Tap any transaction to re-categorize it. The AI learns from corrections and applies them to future imports.
4. **Smart Deduplication** -- Import anytime, multiple times per month. The app silently skips transactions already in the system.
5. **Category Overview (Main View)** -- A clean list of categories with their total amounts for the current month. Income and expenses unified in one view. This is the home screen.
6. **3-Level Drill-Down** -- Tap a category to see subcategories (if applicable), then individual transactions (amount, date, merchant).
7. **Monthly View** -- Data organized by month. Navigate between months to compare.
8. **Account Separation** -- Banque Populaire personal, Revolut personal, and Revolut professional are treated as distinct accounts. Personal budget view excludes professional by default.
9. **MCP Server** -- Expose transaction and category data via MCP so Claude can query budget data conversationally. Full read access to transactions, categories, amounts, and trends.

### Out of Scope for MVP

- **Animations** -- Number transitions, drill-down animations, import cascade. Added post-MVP as polish.
- **Inline trend indicators** -- The "↓12%" next to each category. Requires at least 2 months of data to be meaningful anyway.
- **Recurring transaction filter** -- Toggle to view recurring expenses across categories. Post-MVP.
- **Soft spending ceilings** -- Optional budget targets per category. Post-MVP.
- **Month-over-month evolution view** -- Historical comparison charts. Post-MVP.
- **Notifications** -- TBD what's worth notifying about. Post-MVP.
- **Search** -- Find specific transactions. Post-MVP.
- **Bank sync / API** -- Direct bank connection. Explicitly out of scope. CSV import is the model.

### MVP Success Criteria

- Lambert can import CSVs from both banks and see a unified category overview within 2 minutes
- AI categorizes 75%+ of transactions correctly on first import
- AI reaches near-100% accuracy for known merchants after corrections
- The app is used consistently for 3+ months without abandonment
- Claude can answer questions about budget data through the MCP server
- Lambert can identify at least one spending category to adjust based on app visibility

### Future Vision

- **Delight layer** -- Animated numbers, smooth transitions, import cascade, vibrant color palette. The visual personality.
- **Trend indicators** -- Inline trend data on the main view showing category direction.
- **Recurring filter** -- See all subscriptions and fixed costs across categories.
- **Spending ceilings** -- Optional soft targets per category with visual progress.
- **Historical views** -- Month-over-month evolution for each category.
- **Search** -- Full-text search across transactions.
- **Notifications** -- Smart alerts for meaningful spending patterns.
- **MCP write access** -- Allow Claude to not just read but also correct categories or trigger imports.
