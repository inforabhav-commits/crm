# Phase 2 Backlog

This document records future enhancements only. No Phase 2 item is implemented by this roadmap update.

Priority: P1 = near-term value, P2 = planned enhancement, P3 = exploratory. Complexity reflects likely delivery scope and integration risk.

## 1. Advanced Automation

| Item | Priority | Business value | Dependencies | Complexity | Future task |
|---|---|---|---|---|---|
| Approval workflows | P1 | Govern discounts, assignments, and sensitive changes | RBAC, audit, notifications | Medium | P2-01 |
| Escalation rules | P1 | Reduce missed follow-ups and stalled deals | Workflow rules, notifications | Medium | P2-02 |
| SLA rules | P1 | Set measurable response and resolution expectations | Activities, notifications, reporting | High | P2-03 |
| Follow-up sequences | P2 | Standardize repeatable sales outreach | Activities, workflow engine | High | P2-04 |
| Capacity-based assignment | P2 | Balance workload across teams | Teams, reporting, assignment history | High | P2-05 |
| Visual workflow designer | P3 | Let admins model automation without JSON | Workflow engine, validation, UI | High | P2-06 |

## 2. Communication

| Item | Priority | Business value | Dependencies | Complexity | Future task |
|---|---|---|---|---|---|
| SMS integration | P2 | Extend customer outreach channels | Provider, consent, audit | Medium | P2-07 |
| WhatsApp integration | P2 | Support high-engagement messaging | Provider, templates, consent | High | P2-08 |
| Email synchronization | P1 | Keep CRM communication history complete | Mail provider, threading, privacy | High | P2-09 |
| Calendar synchronization | P1 | Connect meetings and follow-ups to calendars | OAuth, activities, conflict handling | High | P2-10 |
| Video meeting integration | P3 | Simplify remote meeting creation | Calendar sync, provider API | Medium | P2-11 |
| Advanced JustCall enhancements | P2 | Improve disposition, recording, and call analytics depth | JustCall account/API capabilities | Medium | P2-12 |

## 3. Sales / Commercial

| Item | Priority | Business value | Dependencies | Complexity | Future task |
|---|---|---|---|---|---|
| Targets and quotas | P1 | Measure individual and team performance | Users, teams, reporting | Medium | P2-13 |
| Forecasting | P1 | Improve revenue planning | Opportunities, stages, targets | High | P2-14 |
| Quotes and proposals | P1 | Shorten commercial cycle time | Products/pricing, approvals, PDFs | High | P2-15 |
| Discount approvals | P1 | Control margin and exception risk | Approval workflows, pricing rules | Medium | P2-16 |
| Pricing rules | P2 | Standardize prices and discounts | Products, currency, approvals | High | P2-17 |
| E-signature | P2 | Complete agreements from the CRM | Proposals, provider integration, audit | High | P2-18 |
| Renewals | P2 | Retain recurring revenue and surface risk | Contracts, dates, workflows | High | P2-19 |

## 4. Customer Service

| Item | Priority | Business value | Dependencies | Complexity | Future task |
|---|---|---|---|---|---|
| Tickets/cases | P1 | Track service work separately from sales | Customers, activities, RBAC | High | P2-20 |
| SLA tracking | P1 | Make service commitments measurable | Tickets, timers, escalation | High | P2-21 |
| Service escalation | P1 | Reduce unresolved customer issues | Tickets, teams, notifications | Medium | P2-22 |
| Knowledge base | P2 | Deflect repeat questions and aid agents | Content governance, search | Medium | P2-23 |
| Complaint history | P2 | Preserve customer risk and resolution context | Tickets, audit, customer 360 | Medium | P2-24 |
| CSAT/NPS | P2 | Measure customer experience trends | Service workflows, messaging | Medium | P2-25 |

## 5. Reporting / Analytics

| Item | Priority | Business value | Dependencies | Complexity | Future task |
|---|---|---|---|---|---|
| Custom report builder | P1 | Let admins answer new business questions | RBAC, query safeguards, exports | High | P2-26 |
| Scheduled reports | P2 | Distribute recurring operational insight | Report builder, mail delivery | Medium | P2-27 |
| Data quality dashboard | P1 | Find incomplete and duplicate CRM data | Import rules, validation, reports | Medium | P2-28 |
| Forecast dashboard | P1 | Give leadership forward-looking visibility | Forecasting, targets, opportunities | High | P2-29 |
| Coaching insights | P2 | Help managers improve agent execution | Activities, calls, permissions | High | P2-30 |

## 6. Mobile / Productivity

| Item | Priority | Business value | Dependencies | Complexity | Future task |
|---|---|---|---|---|---|
| Mobile app | P2 | Support field and mobile users | API/auth strategy, offline design | High | P2-31 |
| Push notifications | P2 | Deliver urgent CRM actions on mobile | Mobile app, notification service | Medium | P2-32 |
| Business card scanning | P3 | Reduce manual contact entry | Mobile app, OCR/privacy | Medium | P2-33 |
| Voice-to-text notes | P3 | Speed up post-call documentation | Mobile app, transcription provider | High | P2-34 |
| Location check-in | P3 | Record field visit context | Mobile permissions, privacy policy | Medium | P2-35 |
| Offline mode | P2 | Keep field work usable without connectivity | Mobile app, sync/conflict model | High | P2-36 |
| Route planning/optimization | P3 | Improve field visit efficiency | Location data, calendar, mapping provider | High | P2-37 |

## 7. AI

| Item | Priority | Business value | Dependencies | Complexity | Future task |
|---|---|---|---|---|---|
| Record summaries | P2 | Reduce time spent reviewing CRM history | Approved AI provider, privacy controls | Medium | P2-38 |
| Email drafting | P2 | Speed personalized communication | Email sync, approval/privacy controls | Medium | P2-39 |
| Meeting preparation | P2 | Improve call and meeting readiness | Customer 360, calendar sync | Medium | P2-40 |
| Activity suggestions | P2 | Improve next-step consistency | Activities, history, AI governance | Medium | P2-41 |
| Data quality suggestions | P1 | Reduce incomplete and inconsistent records | Data quality dashboard, review workflow | Medium | P2-42 |
| Lead scoring | P1 | Prioritize sales effort | Lead history, explainability, governance | High | P2-43 |
| Deal risk prediction | P2 | Surface stalled or threatened opportunities | Opportunities, calls, activity history | High | P2-44 |
| Next best action | P2 | Guide agent execution | Leads, opportunities, activities, governance | High | P2-45 |
| Predictive forecasting | P3 | Improve forecast confidence | Targets, historical data, governance | High | P2-46 |
| Conversational CRM assistant | P3 | Make CRM data and actions easier to access | Search/action permissions, AI governance | High | P2-47 |
| Call transcription/summarization | P2 | Reduce manual call notes | Recording access, transcription provider | High | P2-48 |
| Sentiment/intent detection | P3 | Identify customer risk and buying signals | Transcription, governance, consent | High | P2-49 |
| AI workflow agent | P3 | Automate complex cross-module assistance | Workflow engine, approvals, AI governance | High | P2-50 |

## 8. Platform / Governance

| Item | Priority | Business value | Dependencies | Complexity | Future task |
|---|---|---|---|---|---|
| SSO | P1 | Simplify secure enterprise access | Identity provider, account mapping | High | P2-51 |
| MFA improvements | P1 | Strengthen account protection | Auth/session model, recovery process | Medium | P2-52 |
| Advanced masking | P1 | Limit sensitive data exposure by role | RBAC, field policy, exports | Medium | P2-53 |
| Data classification | P2 | Apply consistent handling rules | Field inventory, governance policy | Medium | P2-54 |
| Retention/anonymization | P1 | Meet privacy and lifecycle obligations | Audit, legal policy, scheduled jobs | High | P2-55 |
| Multi-company | P2 | Support separate business entities | Ownership model, reporting, permissions | High | P2-56 |
| Multi-tenant SaaS | P3 | Enable isolated customer organizations | Tenant architecture, billing, operations | High | P2-57 |
| Archive/legal hold | P2 | Preserve records while reducing active data | Retention policy, audit, storage | High | P2-58 |
| Advanced monitoring | P1 | Improve operational detection and response | Health checks, alert routing, dashboards | Medium | P2-59 |

## Roadmap Summary

- Core CRM: Complete
- JustCall integration: Complete for current scope
- Production readiness: Complete for current planned scope
- Phase 2: Backlog only

No Phase 2 implementation is included in this document.
