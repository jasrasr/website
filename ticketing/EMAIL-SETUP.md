<!--
File: EMAIL-SETUP.md
File Revision: 1.0.0
Modified: 2026-09-15
History:
1.0.0 - Added Microsoft 365 shared-mailbox, Entra app, Exchange Online RBAC, Graph testing, and Hostinger configuration steps.
-->

# Ticketing Email Setup — Microsoft 365 / Microsoft Graph

This guide configures the Ticketing application to send mail through an unlicensed Microsoft 365 shared mailbox using a Microsoft Entra application and Microsoft Graph.

Recommended production design:

- Shared mailbox: `tickets@jasr.me`
- Display name: `JASR Ticketing`
- Authentication: Microsoft Entra application using client credentials
- Sending API: Microsoft Graph
- Permission model: Exchange Online RBAC for Applications
- Scope: only the `tickets@jasr.me` mailbox
- Credential initially: client secret
- Future hardening option: certificate authentication

The shared mailbox does not sign in. The Ticketing web application authenticates as the Entra application and sends through the shared mailbox.

## 1. Create the shared mailbox

In the Microsoft 365 admin center:

1. Go to **Teams & groups > Shared mailboxes**.
2. Create a shared mailbox.
3. Use:
   - Name: `JASR Ticketing`
   - Email: `tickets@jasr.me`
4. Add your own account as a member if you want to open the mailbox manually in Outlook.
5. Do not assign a license unless you later need features or storage that require one.

Wait until the mailbox is fully available in Exchange Online before continuing.

## 2. Create the Entra application

In the Microsoft Entra admin center:

1. Go to **Identity > Applications > App registrations**.
2. Select **New registration**.
3. Name it `JASR Ticketing`.
4. Supported account type: **Accounts in this organizational directory only**.
5. Leave Redirect URI blank.
6. Select **Register**.

On the application Overview page, record:

- **Application (client) ID**
- **Directory (tenant) ID**

You will need both later.

## 3. Create a client secret

In the app registration:

1. Open **Certificates & secrets**.
2. Open **Client secrets**.
3. Select **New client secret**.
4. Description: `JASR Ticketing Web App`.
5. Choose an expiration interval you are prepared to manage.
6. Create the secret.
7. Immediately copy the secret **Value**.

The Value is the actual credential. Do not rely on the Secret ID.

Never commit the client secret to GitHub.

## 4. Do not grant tenant-wide Graph Mail.Send

For this design, do not add Microsoft Graph `Mail.Send` under the Entra app's normal API Permissions page.

The application will receive `Mail.Send` through Exchange Online RBAC for Applications and will be scoped only to the Ticketing mailbox.

If tenant-wide Entra `Mail.Send` is also granted, it can broaden the application's effective access beyond the Exchange RBAC mailbox scope.

## 5. Find the Enterprise Application Object ID

You need two different identifiers:

```text
AppId    = Application / Client ID
ObjectId = Enterprise application's Service Principal Object ID
```

In Entra:

1. Open **Enterprise applications**.
2. Find `JASR Ticketing`.
3. Open it.
4. Copy the **Object ID**.

Do not use the Object ID from the App Registration object for the Exchange `New-ServicePrincipal` step.

## 6. Connect to Exchange Online PowerShell

Install the module if needed:

```powershell
Install-Module ExchangeOnlineManagement -Scope CurrentUser
```

Connect:

```powershell
Import-Module ExchangeOnlineManagement
Connect-ExchangeOnline
```

Use an account with sufficient Exchange Online administrative permissions.

## 7. Verify the shared mailbox

Run:

```powershell
Get-Mailbox tickets@jasr.me |
    Format-List DisplayName,Alias,PrimarySmtpAddress,RecipientTypeDetails
```

Expected result should resemble:

```text
DisplayName          : JASR Ticketing
Alias                : tickets
PrimarySmtpAddress   : tickets@jasr.me
RecipientTypeDetails : SharedMailbox
```

Take note of the Alias. The examples below assume the alias is `tickets`.

## 8. Create the Exchange service-principal reference

Set the IDs:

```powershell
$AppId = "YOUR-APPLICATION-CLIENT-ID"
$ServicePrincipalObjectId = "YOUR-ENTERPRISE-APP-OBJECT-ID"
```

Create the Exchange service-principal reference:

```powershell
New-ServicePrincipal `
    -AppId $AppId `
    -ObjectId $ServicePrincipalObjectId `
    -DisplayName "JASR Ticketing"
```

Verify it:

```powershell
Get-ServicePrincipal |
    Where-Object AppId -eq $AppId |
    Format-List DisplayName,AppId,ObjectId
```

## 9. Create a management scope for only the Ticketing mailbox

Create the scope:

```powershell
New-ManagementScope `
    -Name "JASR Ticketing Mailbox" `
    -RecipientRestrictionFilter "Alias -eq 'tickets'"
```

Verify exactly what the scope contains:

```powershell
Get-Recipient -RecipientPreviewFilter (
    Get-ManagementScope "JASR Ticketing Mailbox"
).RecipientFilter |
    Format-Table Name,Alias,PrimarySmtpAddress,RecipientType
```

You should see only the Ticketing mailbox.

If another mailbox appears, stop and correct the scope before granting permissions.

## 10. Grant scoped Application Mail.Send

Create the role assignment:

```powershell
New-ManagementRoleAssignment `
    -Name "JASR Ticketing Mail Send" `
    -App $ServicePrincipalObjectId `
    -Role "Application Mail.Send" `
    -CustomResourceScope "JASR Ticketing Mailbox"
```

The intended result is:

```text
JASR Ticketing Entra App
        |
        +-- Application Mail.Send
                  |
                  +-- tickets@jasr.me only
```

## 11. Verify authorization

Test the Ticketing mailbox:

```powershell
Test-ServicePrincipalAuthorization `
    -Identity $ServicePrincipalObjectId `
    -Resource tickets@jasr.me |
    Format-Table RoleName,GrantedPermissions,AllowedResourceScope,InScope
```

You want `Application Mail.Send` and `InScope` to show true.

Also test a different mailbox:

```powershell
Test-ServicePrincipalAuthorization `
    -Identity $ServicePrincipalObjectId `
    -Resource anothermailbox@jasr.me |
    Format-Table RoleName,GrantedPermissions,AllowedResourceScope,InScope
```

The other mailbox should not be in scope.

Permission changes can take time to propagate to Microsoft Graph even if the authorization test already shows the expected result.

## 12. Obtain an OAuth token from PowerShell

Set the values:

```powershell
$TenantId = "YOUR-TENANT-ID"
$ClientId = "YOUR-CLIENT-ID"
$ClientSecret = "YOUR-CLIENT-SECRET"
```

Request a token:

```powershell
$TokenBody = @{
    client_id     = $ClientId
    client_secret = $ClientSecret
    scope         = "https://graph.microsoft.com/.default"
    grant_type    = "client_credentials"
}

$Token = Invoke-RestMethod `
    -Method Post `
    -Uri "https://login.microsoftonline.com/$TenantId/oauth2/v2.0/token" `
    -ContentType "application/x-www-form-urlencoded" `
    -Body $TokenBody
```

Verify that a token was returned:

```powershell
$Token.access_token.Length
```

## 13. Send a test message through Microsoft Graph

Create the headers:

```powershell
$Headers = @{
    Authorization = "Bearer $($Token.access_token)"
    "Content-Type" = "application/json"
}
```

Create a message body. Replace `YOUR-TEST-EMAIL` with your destination address.

```powershell
$Body = @{
    message = @{
        subject = "JASR Ticketing Graph Test"
        body = @{
            contentType = "HTML"
            content = @"
<p>This is a test from the JASR Ticketing Entra application.</p>
<p>If you received this, Microsoft Graph app-only sending is working.</p>
"@
        }
        toRecipients = @(
            @{
                emailAddress = @{
                    address = "YOUR-TEST-EMAIL"
                }
            }
        )
    }
    saveToSentItems = $true
} | ConvertTo-Json -Depth 10
```

Send it:

```powershell
Invoke-RestMethod `
    -Method Post `
    -Uri "https://graph.microsoft.com/v1.0/users/tickets@jasr.me/sendMail" `
    -Headers $Headers `
    -Body $Body
```

A successful Graph `sendMail` request normally returns HTTP 202 Accepted.

Verify:

- the destination received the message
- the From address is `tickets@jasr.me`
- the shared mailbox Sent Items contains the message if `saveToSentItems` is honored as expected

## 14. Verify another mailbox is blocked

Repeat the Graph test but change only the sending mailbox in the URL:

```text
/users/someoneelse@jasr.me/sendMail
```

That request should fail authorization.

If it succeeds, review the Entra app's API permissions and verify that tenant-wide Microsoft Graph `Mail.Send` was not granted separately.

## 15. Hostinger configuration plan

Once the PowerShell test succeeds, the Ticketing application should use an instance-local configuration file such as:

```text
ticketing/config/mail.json
```

Example:

```json
{
  "enabled": true,
  "provider": "microsoft_graph",
  "tenantId": "YOUR-TENANT-ID",
  "clientId": "YOUR-CLIENT-ID",
  "clientSecret": "YOUR-CLIENT-SECRET",
  "mailbox": "tickets@jasr.me",
  "fromName": "JASR Ticketing"
}
```

The live `mail.json` must not be committed to GitHub.

The repository should instead eventually contain:

```text
ticketing/config/mail.json.sample
```

and `.gitignore` should exclude:

```gitignore
/config/mail.json
```

The `/config/` directory should also be protected from direct web access.

## 16. Application implementation sequence

After the Graph test succeeds, the next Ticketing development work should be:

1. Add provider-independent mail configuration.
2. Add Microsoft Graph OAuth client-credentials token handling.
3. Add a `sendTicketEmail()` abstraction.
4. Add an agent-only Email Settings / Send Test Email page.
5. Add a new-ticket notification template.
6. Add public-reply notifications.
7. Add assignment/status notifications.
8. Add mail logging for success/failure.
9. Ensure email failure never prevents a ticket from being saved.
10. Later, add inbound reply-by-email support.

Private agent notes must never generate requester notifications.

## 17. Suggested notification subject format

Use the ticket number in the subject so future reply-by-email processing has a reliable identifier:

```text
[#00042] Printer will not print
```

## 18. Future inbound email

Do not grant inbox read permissions yet.

When reply-by-email is implemented later, add only the minimum scoped Exchange application permissions required to read/process the `tickets@jasr.me` mailbox. Keep those permissions restricted to the same mailbox scope.

The future flow would be:

```text
Requester replies to [#00042]
        |
        v
tickets@jasr.me receives message
        |
        v
Ticketing reads mailbox through Graph
        |
        v
Ticket #42 receives a public requester reply
```

## Security notes

- Never commit tenant ID/client ID/client secret configuration containing the secret to a public repository.
- Treat the client secret like a password.
- Rotate it before expiration.
- Prefer certificate authentication later for stronger production security.
- Keep the Entra/Exchange permission scope limited to the Ticketing mailbox.
- Test that another mailbox is denied before putting the integration into production.
- Log mail failures separately from ticket-save failures.
