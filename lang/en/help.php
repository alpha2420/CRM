<?php

/*
| The in-app Help page: topics of questions and answers. Each topic is
| [anchor, title, icon, [[question, answer], ...]]; pages link to their
| topic by anchor. ":days" is replaced with the dormant-after setting.
| To add a language, copy this file to lang/<code>/help.php.
*/

return [
    'topics' => [
        ['getting-started', 'Getting started', 'dashboard', [
            ['How do I set up my workspace?', 'Follow the checklist on your dashboard: add a lead, set your working hours, invite your team, publish your lead form and connect WhatsApp. It takes about five minutes. Then look at Settings → Autopilot, where the routine follow-up work is already switched on.'],
            ['Where do I start each day?', 'Open My day in the menu. It lists everything you have to do today: overdue follow-ups first, then today\'s calls, meetings and to-dos by time, and new leads waiting for a reply.'],
            ['What is the blue "How this page works" box?', 'Every main page explains what it is for, why it helps and how to use it, in a few steps. Click Hide once you know the page; click Show to bring it back. Each page remembers your choice.'],
            ['Where do I change my company name or time zone?', 'Settings → General. Follow-up times, reminders and reports all use the workspace time zone.'],
        ]],
        ['my-day', 'My day and to-dos', 'check-circle', [
            ['What is My day?', 'Your to-do list for today, made for you: follow-ups whose date has come, meetings you booked, to-dos you added, and new leads still waiting for a first reply. The number next to My day in the menu is how much is due today.'],
            ['How do I add a to-do?', 'Type it in the box at the top of My day, or in the To-dos card on a lead, and pick when: Today, Tomorrow, Next Monday, a time of your choice, or no date. Tick the circle when it\'s done; a to-do on a lead is then noted in the lead\'s history.'],
            ['Can I give a to-do to someone else?', 'Admins can: choose the person in the drop-down next to the date. Agents add to-dos for themselves.'],
            ['Why is a lead under "New leads waiting"?', 'It came in by itself (form, ad, WhatsApp) and nobody has replied yet. The time shows how long it has waited, counting working hours only. Reply within 5 minutes if you can: people who get a quick answer are far more likely to buy.'],
        ]],
        ['leads', 'Adding and working leads', 'leads', [
            ['What are the ways to add leads?', 'By hand (Leads → Add lead), from a spreadsheet (Settings → Import & export), from your website form, WhatsApp, Facebook/Instagram lead ads, Google Ads lead forms, or the Developer API. All of them assign the lead to an agent automatically.'],
            ['What happens if the same person enquires twice?', 'The phone number is recognised and the new enquiry is added to the existing lead\'s history instead of creating a duplicate.'],
            ['How do I log a call?', 'Open the lead, pick the outcome (for example Contacted or Interested), add a short note and the next follow-up date, and save. Quick buttons set tomorrow, 3 days or next week. From My day, tap Log call.'],
            ['How are new leads shared out?', 'By default agents take turns. Under Settings → Lead routing you can add rules, for example "Facebook leads from Pune go to Asha and Ravi", and set a limit on open leads per person. Anyone can pause their own new leads from their name at the bottom left ("I\'m away"); they are skipped until they\'re back.'],
            ['What do Fresh, In progress and Dormant mean?', 'Fresh: not contacted yet. In progress: followed up recently. Dormant: open but no follow-up for :days days. Won and Lost are closed leads.'],
            ['Which ad or campaign brought a lead?', 'Look for Campaign in the lead\'s details. It is filled in by itself for Click-to-WhatsApp ads, Facebook and Instagram lead ads and Google lead forms. For your website form, share the link with ?utm_campaign=name at the end (for example …/f/abc?utm_campaign=diwali-offer).'],
            ['Can the CRM send leads to my other apps?', 'Yes. Settings → Webhooks: paste the address another app gives you (Zapier, Make, Google Apps Script or your own software), pick events such as "a new lead arrives" or "a lead is won", and use Send test. Each message is signed, so the other app can check it came from you.'],
            ['How do I book a meeting or site visit?', 'Open the lead and use the Meetings card. It becomes the lead\'s next follow-up and shows on everyone\'s dashboard and My day that day. Pick a reminder template under Settings → Autopilot (Meeting reminders) and the lead gets a WhatsApp reminder a day and an hour before; the owner is reminded an hour before. Afterwards, mark it done or no-show.'],
            ['Why does it ask why a lead was lost?', 'So Reports can show which reasons cost you most (price, competitors, timing…). Edit the list under Settings → Lost reasons. Each reason can also have a win-back delay: turn on Win back lost leads under Autopilot and, for example, a lead lost on price comes back to its owner after 30 days with a WhatsApp message.'],
            ['What is the lead score?', 'A number from 0 to 100 that shows how likely a lead is to buy: 70 and above is hot, 40 to 69 warm, below 40 cold. Recent WhatsApp replies, recent follow-ups, a later stage, a bigger deal, a source that usually converts, high priority and a hot AI rating raise it; going quiet lowers it. Open a lead to see exactly why it has its score, and sort the lead list by "Highest score" to call the best leads first.'],
            ['Can I add my own fields?', 'Yes. Settings → Custom fields: text, number, date or dropdown. They appear on every lead and in imports and exports.'],
        ]],
        ['whatsapp', 'WhatsApp', 'whatsapp', [
            ['How do I connect WhatsApp?', 'Settings → Integrations → WhatsApp Business. Follow the steps on that page, save, then click Test connection. You need a Meta Business account and a number that is not already using the WhatsApp app.'],
            ['Why can\'t I type a free message?', 'WhatsApp allows free messages only within 24 hours of the lead\'s last message. Outside that window, send an approved template; when the lead replies, free messages open again.'],
            ['What happens when a lead sends a voice note, photo or document?', 'It appears in the chat: photos and videos show in place, voice notes can be played, and documents can be downloaded. With "Write down voice notes" on (Settings → Autopilot), every voice note also gets a written version and a one-line summary, in Hindi, Hinglish or English, so you can read it in a meeting.'],
            ['What happens if a lead replies STOP?', 'They get a short confirmation and nothing automatic contacts them again: no sequences, reminders, win-back messages or templates. You can still answer if they write to you. If they reply START, messages are allowed again. You can also stop messages from the lead page.'],
            ['How do I greet every new lead automatically?', 'Create an automation: "When a new lead arrives → send WhatsApp template".'],
        ]],
        ['ads', 'Facebook, Instagram and Google ads', 'megaphone', [
            ['How do ad leads arrive?', 'Once connected under Settings → Integrations, leads from your ad forms appear within seconds, with the form answers saved in the lead\'s notes and the campaign name in its details.'],
            ['How do I test the connection?', 'Facebook: use Meta\'s Lead Ads Testing Tool. Google: click "Send test data" in the lead form\'s webhook settings.'],
            ['Do Click-to-WhatsApp ads work?', 'Yes. When someone taps your ad and writes to you, the lead is added with the source "WhatsApp ad" and the ad\'s headline as its campaign, so Reports can show which ads bring buyers.'],
        ]],
        ['reports', 'Reports and speed to lead', 'reports', [
            ['What does "Answered in 5 min" mean?', 'The share of leads that came in by themselves (forms, ads, WhatsApp) and got their first reply within 5 minutes. Only working hours count, so a message at 2 a.m. answered at 10:05 counts as 5 minutes. Leads you add by hand are left out: you have already spoken to them.'],
            ['How do I see which ads and campaigns work?', 'Reports → Campaigns & ads lists each campaign with its leads, how many were contacted and won, and the value won. Click a campaign to see its leads.'],
            ['Where can I see each person\'s results?', 'Reports → Team: leads, follow-ups logged, median first reply, share answered within 5 minutes, wins and win rate per person.'],
        ]],
        ['automations', 'Autopilot, automations and reminders', 'zap', [
            ['What does Autopilot do?', 'Routine work, on its own: it plans the first call for new leads, passes on leads nobody answered in time, plans the next follow-up when you forget the date, moves leads to Contacted after your first WhatsApp message, reopens lost leads that come back, nudges quiet leads, can close dead ones, replies when you are away, writes down voice notes, hands over leads when someone leaves, and sends a 9:00 summary every morning. Turn each one on or off under Settings → Autopilot.'],
            ['How do I know what Autopilot did?', 'Every step appears in the lead\'s history, marked Autopilot, and in Settings → Activity log.'],
            ['Autopilot or automations?', 'Autopilot covers the common jobs with one switch each. Automations are your own "when this happens, do that" rules for anything specific, for example sending a template to leads from one source.'],
            ['What is a sequence?', 'A series of follow-ups that runs by itself over several days, for example: day 0 send the welcome template, day 2 remind the owner to call, day 7 send an offer. Build them under Settings → Sequences, then start one from a lead, from the lead list (select leads, then Start sequence) or automatically with an automation. It stops by itself when the lead replies (if you choose) or is won or lost, and only runs inside working hours.'],
            ['What can automations do?', 'They run when a lead arrives, changes status, sends a WhatsApp message (optionally only when it mentions words like "price"), has been quiet for some days, or has a follow-up overdue by some hours. You can limit them by source, status, priority, deal value, city or a custom field. They can assign the lead, change its status or priority, send a WhatsApp template, schedule a follow-up, start a sequence or notify someone.'],
            ['When do reminders arrive?', 'When a follow-up is due, the assigned agent gets an in-app notification, an email and, if turned on, a phone notification. The 9:00 morning summary lists the day\'s follow-ups and to-dos.'],
        ]],
        ['privacy', 'Privacy and consent', 'shield', [
            ['What does India\'s data protection law (DPDP Act) ask of me?', 'Collect contact details with consent, stop when someone asks, show or delete a person\'s data when they ask, keep it no longer than needed, and keep it safe. Settings → Privacy & consent shows how the CRM does each of these for you.'],
            ['A lead asked us to delete their data. What do I do?', 'An admin opens the lead and chooses ⋯ → Erase personal data. Their name, phone, notes and messages are removed for good; the lead stays in your reports without its details.'],
            ['A lead asked what data we hold about them.', 'An admin opens the lead and chooses ⋯ → Download their data. You get a file with everything: details, consent, history, messages and meetings, which you can send to them.'],
            ['Can old leads be removed automatically?', 'Yes. Settings → Privacy & consent → How long to keep closed leads. Leads won or lost longer ago than you choose are erased every night.'],
        ]],
        ['shortcuts', 'Shortcuts and dark mode', 'monitor', [
            ['How do I jump to a lead quickly?', 'Press Ctrl K (⌘ K on a Mac), or click the Ctrl K hint in the search bar, and type a name, phone number or company. The same box takes you to any page: type "import", "autopilot" or "reports".'],
            ['Which keyboard shortcuts are there?', '/ search · N new lead · T new to-do · G then D, M, L, I, R or S to go to Dashboard, My day, Leads, Inbox, Reports or Settings · ? the full list.'],
            ['How do I turn on dark mode?', 'Click your name at the bottom left and choose Dark, or Device to follow your phone or computer setting.'],
        ]],
        ['team', 'Team and security', 'users', [
            ['How do I add my team?', 'Settings → Team → Invite by email. They choose their own password. You can also copy the invite link and send it on WhatsApp.'],
            ['What can agents see?', 'Only the leads assigned to them. Admins see everything and manage settings.'],
            ['How do I turn on two-factor login?', 'Your name (bottom left) → Security → Set up two-factor login, and scan the QR code with Google or Microsoft Authenticator. Admins can require it for everyone under Settings → General.'],
            ['Where can I see who changed what?', 'Settings → Activity log.'],
        ]],
        ['mobile', 'Using it on your phone', 'smartphone', [
            ['Is there an app?', 'Open the site in your phone\'s browser and choose "Add to Home Screen" (Safari) or "Install app" (Chrome). It opens like an app.'],
            ['How do I get notifications on my phone?', 'Notifications → "Turn on for this device", and allow notifications when asked. On iPhone, add the app to your home screen first.'],
        ]],
        ['data', 'Your data', 'download', [
            ['How do I download everything?', 'Settings → General → Your data. You get a ZIP of spreadsheets: leads, follow-ups, messages, team, settings and the activity log.'],
            ['How do I close my account?', 'Settings → General → Delete workspace. This permanently deletes every lead, message and user, so download your data first.'],
        ]],
    ],
];
