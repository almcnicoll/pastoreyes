<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PastorEyes — Privacy Policy</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 16px;
            line-height: 1.7;
            color: #1a1a1a;
            background: #fff;
            max-width: 800px;
            margin: 0 auto;
            padding: 48px 32px 80px;
        }
        header {
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 24px;
            margin-bottom: 40px;
        }
        .app-name {
            font-size: 13px;
            font-family: Arial, sans-serif;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #555;
            margin-bottom: 8px;
        }
        h1 {
            font-size: 28px;
            font-weight: normal;
            margin-bottom: 8px;
        }
        .meta {
            font-size: 13px;
            color: #666;
            font-family: Arial, sans-serif;
        }
        h2 {
            font-size: 18px;
            font-weight: bold;
            margin-top: 40px;
            margin-bottom: 12px;
            font-family: Arial, sans-serif;
        }
        h3 {
            font-size: 15px;
            font-weight: bold;
            margin-top: 24px;
            margin-bottom: 8px;
            font-family: Arial, sans-serif;
        }
        p { margin-bottom: 14px; }
        ul {
            margin: 0 0 14px 24px;
        }
        li { margin-bottom: 6px; }
        .highlight-box {
            background: #f7f7f7;
            border-left: 4px solid #1a1a1a;
            padding: 16px 20px;
            margin: 24px 0;
            font-size: 15px;
        }
        .contact-block {
            background: #f0f0f0;
            padding: 20px 24px;
            margin-top: 40px;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }
        a { color: #1a1a1a; }
        @media print {
            body { padding: 20px; }
        }
    </style>
</head>
<body>

<header>
    <p class="app-name">PastorEyes</p>
    <h1>Privacy Policy</h1>
    <p class="meta">Last updated: <strong>November 2026</strong> &nbsp;|&nbsp; Applies to: pastoreyes.almcnicoll.co.uk</p>
</header>

<div class="highlight-box">
    <strong>Summary for busy readers:</strong> PastorEyes stores personal and sensitive information about third parties on your behalf. All sensitive data is encrypted using a key unique to your account, meaning neither the developer nor anyone with access to the database can read your records. You can delete your account and all associated data at any time by emailing the developer. We do not sell your data, use it for advertising, or use it for any purpose other than operating the service. It is shared only with the services needed to run PastorEyes, listed in section 8.
</div>

<h2>1. Who We Are</h2>
<p>PastorEyes is a personal pastoral care tool operated by Al McNicoll as an individual developer (&ldquo;the developer,&rdquo; &ldquo;we,&rdquo; &ldquo;us&rdquo;). It is provided as a free service on a self-hosted basis at <a href="https://pastoreyes.almcnicoll.co.uk">pastoreyes.almcnicoll.co.uk</a>.</p>
<p>This service is not operated by a registered company and is provided for personal, non-commercial pastoral use. The developer is based in the United Kingdom.</p>

<h2>2. What Data We Collect and Why</h2>

<h3>2.1 Your Account Data</h3>
<p>When you sign in using Google OAuth, we receive and store:</p>
<ul>
    <li>Your name (first and last), as provided by Google</li>
    <li>Your email address</li>
    <li>A Google account identifier, used to recognise you on return visits</li>
    <li>OAuth access and refresh tokens (stored encrypted), which allow the application to access your Google Contacts and Google Calendar on your behalf until you disconnect Google or delete your account</li>
</ul>
<p>Your name is stored in an encrypted form. Your email address is stored unencrypted, as it is required for account look-up at sign-in.</p>

<h3>2.2 Data You Enter About Third Parties</h3>
<p>The core purpose of PastorEyes is to help you record information about people in your pastoral care. This may include:</p>
<ul>
    <li>Names, including former and alternative names</li>
    <li>Addresses</li>
    <li>Dates of birth, death, anniversaries, and other key dates</li>
    <li>Notes about meetings, conversations, and personal circumstances</li>
    <li>Prayer needs and their outcomes</li>
    <li>Mentoring goals and progress</li>
    <li>Relationship connections between individuals</li>
    <li>Photographs</li>
</ul>
<p>This data relates to third parties who have not themselves consented to its storage in PastorEyes. As the user, <strong>you are solely responsible for ensuring you have a legitimate purpose for recording this information</strong>, and for handling it in a manner that respects the privacy and dignity of the individuals concerned.</p>

<h3>2.3 Google Integration Data</h3>
<p>When you sign in, Google asks you to allow PastorEyes the access below. Each permission is used only for the feature described next to it.</p>

<p><strong>Sign-in (name, email address, Google account ID).</strong> Used to create and recognise your account (see 2.1).</p>

<p><strong>Google Contacts</strong> (scope: <code>https://www.googleapis.com/auth/contacts</code>):</p>
<ul>
    <li><strong>Searching.</strong> When you use &ldquo;Link to Google Contact&rdquo; or &ldquo;Add from Google Contacts&rdquo;, PastorEyes searches your contacts by the name you type and shows the matches (names, photos, email addresses and phone numbers) so you can pick one. Email addresses and phone numbers are displayed only; they are not saved in PastorEyes.</li>
    <li><strong>Importing and linking.</strong> When you import or link a contact, PastorEyes reads that contact and saves its names (including middle name and nickname), photo, addresses, birthday (as a Birthday key date), other dated events such as anniversaries, and gender into the person&rsquo;s PastorEyes record, together with the contact&rsquo;s Google identifier.</li>
    <li><strong>Background comparison.</strong> Once an hour, for people you have linked to a Google Contact, PastorEyes reads that contact in the background and compares its names, birthday, addresses and photo with your PastorEyes record. Any differences found are saved (encrypted) in a review list until you choose to pull the Google value into PastorEyes, push the PastorEyes value to Google, or ignore it.</li>
    <li><strong>Writing back.</strong> Only when you choose to push a flagged difference to Google does PastorEyes change that contact&rsquo;s name, birthday or address in Google Contacts. PastorEyes never creates or deletes Google contacts and changes nothing else.</li>
</ul>

<p><strong>Google Calendar</strong> (scopes: <code>https://www.googleapis.com/auth/calendar.calendarlist.readonly</code> and <code>https://www.googleapis.com/auth/calendar.events</code>):</p>
<ul>
    <li><strong>Listing calendars.</strong> PastorEyes lists the calendars you can edit so you can choose where key dates are synced. Calendar names are displayed only; the identifier of the calendar you choose is saved.</li>
    <li><strong>Creating, updating and deleting events.</strong> When you choose to sync a key date, PastorEyes creates an event in your chosen calendar. The event title is the key date&rsquo;s label or type, and the event description lists the names of the people the key date concerns. PastorEyes updates or deletes that event when you change or delete the key date in PastorEyes, or choose a different calendar. The event&rsquo;s Google identifier is saved.</li>
    <li><strong>Reading changes back.</strong> When you open a person&rsquo;s page, PastorEyes re-reads the events it created and, if you have changed an event&rsquo;s date or title in Google Calendar, copies that change into the matching key date.</li>
    <li>PastorEyes does not read, copy or change any other event in your calendars.</li>
</ul>

<p>PastorEyes does not store any other Google data. Apart from what you import, the only Google contact data it keeps is the Google identifier of linked contacts and the differences saved in the review list described above.</p>

<h3>2.4 Limited Use of Google User Data</h3>
<p>PastorEyes&rsquo;s use and transfer to any other app of information received from Google APIs will adhere to the <a href="https://developers.google.com/terms/api-services-user-data-policy#additional_requirements_for_specific_api_scopes">Google API Services User Data Policy</a>, including the Limited Use requirements. In particular, for data received from Google Contacts and Google Calendar:</p>
<ul>
    <li>We use it only to provide the user-facing features described in section 2.3.</li>
    <li>We do not transfer it to anyone else, except as necessary to provide those features (see section 8), for security purposes such as investigating abuse or a bug, to comply with the law, or with your explicit consent.</li>
    <li>We do not use it, or sell it, for advertising (including retargeting, personalised or interest-based advertising), and we do not sell it to data brokers or information resellers.</li>
    <li>We do not use it to decide credit-worthiness or lending.</li>
    <li>We do not use it to develop, improve or train generalised artificial intelligence or machine-learning models. PastorEyes does not use AI or machine learning on your data.</li>
    <li>No human reads it, except where you have given specific permission, where it is necessary for security purposes or to comply with the law, or where the data is aggregated and anonymised for internal operations.</li>
</ul>

<h3>2.5 API Access (Optional)</h3>
<p>PastorEyes can give you a personal API token (Settings &rarr; API) so that a program or AI assistant that <em>you</em> choose can read, and optionally add to, your records. Tokens are off until you create one. Each token is limited to your own data, can be read-only, can expire, and can be revoked at any time. Only a one-way hash of a token is kept. PastorEyes keeps a log of what each token did (the time, the kind of request and a count such as &ldquo;created a Note for 2 people&rdquo;), which you can see in Settings; it does not record names or the text of your records.</p>
<p><strong>What leaves PastorEyes when you use this.</strong> The API returns your records to the program holding the token in readable form, not encrypted. If that program is an AI assistant, whatever it reads, which can include information imported from Google Contacts (which is part of your records), is processed by the assistant&rsquo;s provider under that provider&rsquo;s own terms. PastorEyes itself does not send your data to any AI service and does not use AI on your data; this happens only if you create a token and give it to such a service, and you are responsible for that choice. Entries added through a token are marked as added through the API.</p>

<h2>3. How Your Data Is Protected</h2>

<h3>3.1 Encryption</h3>
<p>All sensitive fields (names, notes, addresses, dates, photographs, and similar) are encrypted at rest using AES-256-CBC encryption. The encryption key for each user is derived from a combination of a server-side application key and a unique random salt stored against your account. <strong>Neither the application key alone nor the database alone is sufficient to decrypt your data.</strong></p>
<p>This means that if the database were accessed without authorisation, your sensitive data could not be read without also having the server configuration. Equally, the developer cannot read your pastoral records.</p>

<h3>3.2 Access Control</h3>
<p>Each user&rsquo;s data is completely separate from other users&rsquo; data. No user can access another user&rsquo;s records. Access to the application requires authentication via your Google account.</p>

<h3>3.3 Data in Transit</h3>
<p>The application is served over HTTPS. Data transmitted between your browser and the server, and between the server and Google&rsquo;s APIs, is encrypted in transit.</p>

<h3>3.4 Server Security</h3>
<p>The application is hosted on a shared hosting server in the United Kingdom. The developer takes reasonable steps to keep server software up to date and to restrict access to server configuration. However, as a self-hosted individual project, no formal security audit has been conducted, and the service is offered without warranty as to its security.</p>

<h2>4. Your Rights</h2>
<p>Under the UK General Data Protection Regulation (UK GDPR) and the Data Protection Act 2018, you have the following rights in relation to your personal data:</p>
<ul>
    <li><strong>Right of access:</strong> You may request a copy of the personal data we hold about you.</li>
    <li><strong>Right to erasure:</strong> You may request deletion of your account and all associated data. You can do this yourself within the application, or by emailing the developer. Deletion is permanent and irreversible, and also revokes PastorEyes&rsquo;s access to your Google account.</li>
    <li><strong>Right to rectification:</strong> You may correct your account name and other personal details within the application settings.</li>
    <li><strong>Right to restrict processing:</strong> You may request that we restrict processing of your data in certain circumstances.</li>
    <li><strong>Right to data portability:</strong> PastorEyes includes a data export feature that allows you to download all your data in an encrypted portable format.</li>
    <li><strong>Right to object:</strong> You may object to processing of your data where it is based on legitimate interests.</li>
</ul>
<p>To exercise any of these rights, please email the developer at the address below. We will respond within one calendar month.</p>

<h2>5. Legal Basis for Processing</h2>
<p>We process your personal data on the following legal bases:</p>
<ul>
    <li><strong>Contract:</strong> Processing your account data is necessary to provide the service you have signed up for.</li>
    <li><strong>Legitimate interests:</strong> We have a legitimate interest in maintaining the security and integrity of the service.</li>
    <li><strong>Your explicit consent:</strong> You consent to Google integration at the point of signing in with Google OAuth, and may withdraw this at any time by disconnecting your Google account in Settings (which also revokes PastorEyes&rsquo;s access at Google), or from your Google Account&rsquo;s <a href="https://myaccount.google.com/permissions">third-party access page</a>.</li>
</ul>

<h2>6. Data We Do Not Collect</h2>
<ul>
    <li>We do not use cookies for advertising or tracking purposes.</li>
    <li>We do not share your data with any third parties other than Google (for the Google integration features you have authorised) and the hosting and email services needed to run PastorEyes, described in section 8.</li>
    <li>We do not use your data, or the data you record about others, for any purpose other than operating the service.</li>
    <li>We do not contact individuals whose information is stored in your PastorEyes records.</li>
    <li>We do not sell data or use it for commercial purposes.</li>
</ul>

<h2>7. Data Retention</h2>
<p>Your data is retained for as long as your account exists. If you delete your account, all associated data is permanently deleted from the database. Deletion cannot be undone.</p>
<p>Automated database backups may retain copies of data for a short period after deletion. These backups exist solely for disaster recovery and are not accessible to other users.</p>
<p>Your Google OAuth tokens are deleted, and revoked at Google, when you disconnect Google in Settings or delete your account. Information you have imported from Google Contacts becomes part of your PastorEyes records and is kept until you delete it or your account. Calendar events that PastorEyes created remain in your Google Calendar after you disconnect or delete your account; delete them in Google Calendar if you no longer want them.</p>

<h2>8. Third-Party Services</h2>
<p>PastorEyes integrates with Google&rsquo;s services (Google Contacts and Google Calendar). Your use of these features is also governed by Google&rsquo;s own privacy policy, available at <a href="https://policies.google.com/privacy">policies.google.com/privacy</a>.</p>
<p><strong>Hosting.</strong> PastorEyes runs on a shared hosting server in the United Kingdom, which stores the database and processes all data on our behalf.</p>
<p><strong>Email delivery.</strong> If you opt in to Prayer Emails (Settings &rarr; Prayer Emails; they are off unless you turn them on), PastorEyes emails the address on your account a list of the people who have active prayer needs, showing their names and a count, with links back to PastorEyes. The email is sent through the email (SMTP) service configured by the administrator, which therefore handles your email address and those names. The names come from your PastorEyes records, which may include names you imported from Google Contacts. You can turn Prayer Emails off at any time.</p>
<p><strong>Programs and AI assistants you connect.</strong> If you create an API token (section 2.5) and give it to a program or AI assistant, that service receives the records the token can read. PastorEyes does not choose or control such a service.</p>
<p>No other third-party analytics, advertising, or tracking services are used.</p>

<h2>9. Children</h2>
<p>PastorEyes is not intended for use by persons under the age of 18. You must be 18 or over to create an account. If you are aware that a minor has created an account, please contact the developer.</p>

<h2>10. Changes to This Policy</h2>
<p>This privacy policy may be updated from time to time. Significant changes will be communicated by updating the &ldquo;Last updated&rdquo; date at the top of this document. Continued use of the service after a change constitutes acceptance of the updated policy.</p>

<h2>11. Complaints</h2>
<p>If you are unhappy with how your data is handled, you have the right to lodge a complaint with the Information Commissioner&rsquo;s Office (ICO), the UK&rsquo;s supervisory authority for data protection. Details are available at <a href="https://ico.org.uk">ico.org.uk</a>.</p>

<div class="contact-block">
    <strong>Contact the Developer</strong><br><br>
    For data requests, account deletion, or privacy queries, please email:<br>
    <strong>almcnicoll@gmail.com</strong><br><br>
    Please include &ldquo;PastorEyes Privacy&rdquo; in the subject line. We aim to respond within one calendar month.
</div>

</body>
</html>
