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
