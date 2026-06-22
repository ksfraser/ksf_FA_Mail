# Business Requirements — ksf_FA_Mail

**Version:** 2.0.0
**Date:** 2026-06-21

## BR-001: Reliable outbound email from FA
FrontAccounting's default mail path (`/usr/sbin/sendmail`) is frequently absent
or misconfigured in containerised deployments. FA also lacks SMTP auth support
natively. The business needs a drop-in mail module that supports authenticated
SMTP sending with a clear fallback chain.

## BR-002: Per-user and team sending identity
Different users and teams (Sales, Support, Management) need to send from
different email addresses/identities. The system must support personal SMTP
accounts, shared team/role accounts, and a system fallback — all selectable
at send-time via a dropdown.

## BR-003: Loose coupling with other KSF modules
The mail module must not hard-code knowledge of teams, CRM contacts, or HRM
structures. Other modules inject sender options and resolve configs via FA's
hook system, keeping the mail module agnostic.

## BR-004: iCal calendar invitation support
The module must support sending multipart iMIP/iTip calendar invitations as
part of the ksf_FA_Calendar event workflow, using the same sender selection.

## BR-005: CASL-compliant sender identification
All outgoing emails must include a footer identifying the sender (individual
name, organisation name, mailing address, phone, email) — sourced from the FA
users table and company preferences — to comply with Canadian Anti-Spam
Legislation (CASL) requirements.

## BR-006: Pre-flight verification without sending
Administrators must be able to test SMTP authentication/connectivity without
sending an email, and separately send a test email to a specific recipient
with explicit confirmation (CASL consent) before delivery.
