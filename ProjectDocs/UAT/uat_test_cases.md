# ksf_FA_Mail — UAT Test Cases

## TC-001: Setup Page Access
1. Login as admin
2. Navigate to System → Mail Sending Setup
3. Verify the page loads with mail type selector

## TC-002: SMTP Configuration
1. Set Mail Type to "SMTP server"
2. Enter valid SMTP host, port, username, password
3. Select TLS encryption
4. Click Update
5. Verify "The mail sending settings has been updated." notification

## TC-003: SMTP Validation
1. Set Mail Type to "SMTP server"
2. Leave SMTP Host empty
3. Click Update
4. Verify validation error message

## TC-004: PHP Mail Mode
1. Set Mail Type to "PHP's mail function"
2. Click Update
3. Verify SMTP fields are hidden

## TC-005: BCC Configuration
1. Enter a BCC email address
2. Click Update
3. Verify setting persists after page reload

## TC-006: Email Sending (requires configured MTA)
1. Configure SMTP or ensure PHP mail works
2. Trigger email from another module (e.g., send iCal invite from calendar)
3. Verify email is received at the destination

## TC-007: Fallback Behaviour
1. Set Mail Type to "SMTP server" with invalid credentials
2. Configure PHP mail as fallback
3. Trigger email
4. Verify email is delivered via PHP mail (check mail log)

## TC-010: Personal SMTP Setup
1. Login as non-admin user
2. Navigate to My Mail Settings
3. Configure personal SMTP host, port, username, password
4. Click Update
5. Verify settings persist after page reload
6. Re-open page and verify fields are pre-filled (password masked)

## TC-011: Sender Dropdown Rendering
1. Login as a user with personal SMTP configured
2. Open compose-email UI (e.g., calendar event modal)
3. Verify dropdown contains:
   - "System Account"
   - "Personal Account (user@example.com)"
4. Select "Personal Account" and send
5. Verify email is sent via personal SMTP

## TC-012: Hook-Extended Sender (requires other module)
1. Ensure ksf_FA_CRM (or another hook-providing module) is active
2. Open compose-email UI
3. Verify dropdown includes hook-injected accounts
4. Select a hook-injected sender
5. Verify email is sent via the resolved config

## TC-013: SMTP Test Settings (auth only, no email)
1. Configure SMTP with valid host, port, credentials
2. Click "Test Settings" without clicking Update first
3. Verify green notification: "SMTP settings verified — server connection and authentication succeeded."
4. Verify no email was sent (check inbox)
5. Change SMTP host to invalid, click "Test Settings"
6. Verify red error message
7. Switch Mail Type to "PHP's mail function"
8. Click "Test Settings"
9. Verify amber warning: "SMTP is not selected — no server to test."

## TC-014: Send Test Email with CASL Confirm
1. Configure valid SMTP
2. Enter a test recipient email in the "Test recipient" field (pre-filled with current user's email)
3. Click "Send Test Email"
4. Verify amber warning appears with recipient address and CASL notice
5. Click "Cancel" — verify no email sent
6. Click "Send Test Email" again
7. Click "Confirm Send" — verify green notification and email received
8. Verify from address is the SMTP username (or current user email)
9. Verify footer in received email contains sender name, company, address, phone, email
10. Clear test recipient field, click "Send Test Email"
11. Verify amber warning: "Please enter a valid email address."

## TC-015: CASL Footer on Outgoing Emails
1. Send any email via the module (test email, iCal invite, etc.)
2. Open the received email
3. Verify footer at bottom contains:
   - "Sent by <user name>"
   - Company name from company preferences
   - Postal address from company preferences
   - Phone number (user table or company prefs)
   - Email address (user table or company prefs)
4. Verify footer shows correct user data when user email/phone differ from company settings

## TC-016: Mail Type DDL Toggle
1. Switch Mail Type to "SMTP server"
2. Verify SMTP fields (host, port, secure, username, password) appear instantly without page reload
3. Switch Mail Type to "PHP's mail function"
4. Verify SMTP fields hide instantly
