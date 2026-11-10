# EmailJS login OTP setup

## EmailJS dashboard

1. In **Email Services**, connect the mailbox that will send SKalinga sign-in codes and verify that it can send mail.
2. In **Email Templates**, create a template named `SKalinga Login OTP`. Configure **To Email** as `{{to_email}}`.
3. Use these template variables exactly:
   - `to_email`
   - `user_name`
   - `otp_code`
   - `expires_minutes`
4. Set the subject to `Your SKalinga sign-in code`.
5. Use this template content:

   ```text
   Hello {{user_name}},

   Your SKalinga sign-in code is:

   {{otp_code}}

   This code expires in {{expires_minutes}} minutes.

   If this wasn't you, ignore this email and change your password.
   ```

   Do not add a sign-in link or put the code in the subject.
6. Copy the service ID, template ID, and public key from the EmailJS dashboard.
7. In **Account → Security**, enable the private key for server-side requests and enable non-browser applications. EmailJS will reject Render requests if non-browser access is disabled.
8. Configure the sender address in the selected EmailJS service and test delivery to a mailbox you control.

## Application environment

Set the following environment variables in Render. For local XAMPP, use the ignored `.env` or `includes/config.local.php`. Never commit the private key or expose it in browser code.

| Variable | Value |
|---|---|
| `MAIL_DRIVER` | `emailjs` |
| `LOGIN_OTP_MODE` | `email` |
| `EMAILJS_SERVICE_ID` | EmailJS service ID |
| `EMAILJS_TEMPLATE_ID` | Login OTP template ID |
| `EMAILJS_PUBLIC_KEY` | EmailJS public key |
| `EMAILJS_PRIVATE_KEY` | Private key from Account → Security |
| `EMAILJS_MONTHLY_LIMIT` | Monthly request budget; defaults to `200` |

`MAIL_DRIVER=log` is local-development-only and is rejected in production. It deliberately does not log or pretend to send OTP codes. EmailJS's free plan allows 200 requests per month; the application reserves each send attempt in `mail_usage`, enforces a one-second global interval, and disables sends at the configured monthly cap.

## Smoke test

After applying the PostgreSQL migrations, sign in with a test account and verify that the email arrives, the code expires after ten minutes, and successful verification completes login. The super-admin Settings page includes a **Send test email to my account** button and displays the EmailJS HTTP result without exposing credentials or the code. A test email consumes the same monthly quota as a login code.
