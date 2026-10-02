<?php

/*
| "How this page works" for each screen: what it is, why it helps and how
| to use it. **Text in stars** is shown in bold (the names of buttons and
| sections). To add a language, copy this file to lang/<code>/guide.php.
*/

return [
    'title' => 'How this page works',
    'why' => 'Why it helps',
    'hide' => 'Hide',
    'show' => 'Show',
    'more' => 'More in Help',

    'dashboard' => [
        'what' => 'Your business at a glance: what needs attention now, and how sales are going.',
        'why' => 'You see the most urgent work first, so no lead or meeting is forgotten.',
        'steps' => [
            'Start with **Today\'s follow-ups**: tap the phone or WhatsApp button, then open the lead and log what happened.',
            'Check **Speed to lead**. Leads under **Waiting for a first reply** should get a call or message within 5 minutes.',
            'The four tiles at the top open the matching leads when you click them.',
            'For your full to-do list, open **My day** in the menu.',
        ],
        'help' => 'getting-started',
    ],

    'today' => [
        'what' => 'Everything you have to do today, in one list, oldest first.',
        'why' => 'One place to work from: you never have to search for who to call next.',
        'steps' => [
            'Do the **Overdue** items first: they should have happened already.',
            'For a follow-up, tap the phone button. When you hang up, choose how the call went; the next try is planned for you.',
            'Add anything else as a to-do in the box at the top, and tick the circle when it\'s done.',
            'New leads under **New leads waiting** need a first reply: the faster, the more likely they buy.',
        ],
        'help' => 'my-day',
    ],

    'leads' => [
        'what' => 'All your leads, as a list or a board with one column per stage.',
        'why' => 'Find any lead fast, see where every deal stands, and act on many leads at once.',
        'steps' => [
            'Use the tabs (**Fresh**, **Follow-ups due**, **Dormant**…) to see leads that need the same kind of work.',
            'Search by name, phone or company, or open **More filters** for priority, campaign and dates.',
            'Switch to **Board** to drag a lead to its new stage.',
            'Tick several leads in the list to change their stage, owner or start a sequence together.',
        ],
        'help' => 'leads',
    ],

    'lead' => [
        'what' => 'Everything about one lead: details, history, chat, to-dos and meetings.',
        'why' => 'Anyone in the team can pick up the lead and know exactly what happened and what\'s next.',
        'steps' => [
            'Tap **Call**. When you hang up, choose how it went: **No answer** or **Busy** plans the next try for you; **We talked** opens the follow-up form.',
            'After every conversation, use **Log a follow-up**: pick the outcome, write a short note and choose the next date.',
            'Click a stage in the bar at the top to move the lead; **Mark won** when it\'s closed.',
            'Open the **WhatsApp** tab to chat; voice notes are written down for you.',
            'Use **To-dos** for anything to remember and **Meetings** to book a visit with automatic reminders.',
        ],
        'help' => 'leads',
    ],

    'inbox' => [
        'what' => 'All WhatsApp chats with your leads, newest first.',
        'why' => 'Reply from one place without a phone, and every message stays with the lead.',
        'steps' => [
            'Pick a chat on the left; unread chats are bold with a count.',
            'You can type freely for 24 hours after the lead\'s last message. After that, WhatsApp only allows an approved template.',
            'Photos, documents and voice notes show in the chat. Voice notes come with a written version.',
            'Change the stage or open the full lead from the panel on the right.',
        ],
        'help' => 'whatsapp',
    ],

    'reports' => [
        'what' => 'How your sales are doing for any period: leads, speed, wins and money.',
        'why' => 'See which sources, campaigns and people bring results, so you spend time and money where it works.',
        'steps' => [
            'Pick a period at the top (7 days, 30 days, this month or your own dates).',
            '**Answered in 5 min** shows how fast new leads get a reply; faster replies win more deals.',
            '**Campaigns & ads** compares your ads by leads and won value. Click a campaign to see its leads.',
            'The **Team** table shows each person\'s speed and results.',
        ],
        'help' => 'reports',
    ],

    'broadcasts' => [
        'what' => 'Send one approved WhatsApp template to a group of leads at once.',
        'why' => 'Announce an offer, an event or a price change to the right people in minutes, and see who read and replied.',
        'steps' => [
            'Click **New broadcast**, give it a name and choose an approved template.',
            'Choose who gets it: open leads, customers or one stage, and narrow it by source, campaign or owner. Click **Count leads**.',
            'Check the number and Meta\'s estimated charge, tick the box and send.',
            'Open the broadcast to see sent, delivered, read and replied. People who said STOP never get broadcasts.',
        ],
        'help' => 'whatsapp',
    ],

    'autopilot' => [
        'what' => 'Routine follow-up work the CRM does by itself, one switch each.',
        'why' => 'No lead waits because someone forgot: the CRM plans calls, passes on leads and sends reminders for you.',
        'steps' => [
            'First set your **Working hours**: Autopilot only acts inside them.',
            'Read each switch and turn on the ones that fit your business. Numbers in a sentence can be changed.',
            'Some switches need a WhatsApp template; pick one in the drop-down inside the sentence.',
            'Click **Save Autopilot**. Everything it does is written in the lead\'s history.',
        ],
        'help' => 'automations',
    ],

    'routing' => [
        'what' => 'Who gets each new lead.',
        'why' => 'Leads reach the right person in seconds, and nobody gets more than they can handle.',
        'steps' => [
            'Without rules, new leads go to each agent in turn.',
            'Add a rule, for example **Source is Facebook and city is Pune → Asha and Ravi**. The first matching rule wins.',
            'Use **Give no one more than … open leads** so busy agents are skipped.',
            'Someone on leave can choose **I\'m away** under their name (bottom left); they get no new leads until they are back.',
        ],
        'help' => 'leads',
    ],

    'sequences' => [
        'what' => 'Planned follow-ups that run by themselves over several days.',
        'why' => 'Most sales need 5 or more touches; a sequence makes sure every lead gets them.',
        'steps' => [
            'Click **New sequence** and add steps: send a WhatsApp template, or remind the owner to call.',
            'Set the day of each step, counted from when the lead joins.',
            'Start it from a lead, from the lead list (select leads → **Start sequence**) or with an automation.',
            'It stops by itself when the lead replies (if you choose), or is won or lost.',
        ],
        'help' => 'automations',
    ],

    'automations' => [
        'what' => 'Your own "when this happens, do that" rules.',
        'why' => 'Handle special cases automatically, like sending the price list when someone asks for the price.',
        'steps' => [
            'Click **New automation** and choose **When** it runs (new lead, stage change, WhatsApp message, quiet lead…).',
            'Optionally limit it with **Only if** (source, city, deal value, words in the message…).',
            'Choose what it does under **Then**: assign, change stage, send a template, start a sequence, notify someone.',
            'Rules never trigger each other, so they cannot loop.',
        ],
        'help' => 'automations',
    ],

    'integrations' => [
        'what' => 'Where your leads come from: website form, WhatsApp, Facebook and Google ads, and your own software.',
        'why' => 'Every enquiry lands in the CRM by itself and is assigned at once: no copying from email or sheets.',
        'steps' => [
            'Open **Website form** to get a link you can share or put on your website.',
            'Open **WhatsApp Business** and follow the steps there to connect your number, then click **Test connection**.',
            'Connect **Facebook** or **Google** lead ads so ad leads arrive within seconds, and **IndiaMART** with your CRM key for buyer enquiries.',
            'Developers can send leads to the **Developer API** with your API key.',
        ],
        'help' => 'whatsapp',
    ],

    'webhooks' => [
        'what' => 'Send CRM events to other apps (Zapier, Make, Google Sheets or your own software).',
        'why' => 'Connect the CRM to tools you already use without waiting for a built-in integration.',
        'steps' => [
            'Paste the web address the other app gives you (it must start with https://).',
            'Choose which events to send: a new lead, a stage change, a win…',
            'Click **Send test** to check the other app receives it.',
            'Failed sends are retried, and the last result is shown here.',
        ],
        'help' => 'leads',
    ],

    'custom_fields' => [
        'what' => 'Extra details you want to keep on every lead, like "Budget" or "Course".',
        'why' => 'Record what matters in your business, then filter, route and automate on it.',
        'steps' => [
            'Add a field and choose its type: text, number, date or a drop-down.',
            'It appears on every lead form and lead page.',
            'Use it in routing rules and automations, and as a column in imports and exports.',
        ],
        'help' => 'leads',
    ],

    'lost_reasons' => [
        'what' => 'The reasons a lead can be marked lost.',
        'why' => 'Reports show what costs you the most deals, and some lost leads can be won back later.',
        'steps' => [
            'Rename, add or delete reasons to match your business.',
            'Set **Win back after** days for reasons worth another try, like "Price too high".',
            'Turn on **Win back lost leads** under Autopilot to reopen them automatically.',
        ],
        'help' => 'leads',
    ],

    'statuses' => [
        'what' => 'The stages a lead moves through, from new to won or lost.',
        'why' => 'Stages that match how you really sell make the board and reports meaningful.',
        'steps' => [
            'Rename stages or add your own, and set their order and colour.',
            'Each stage is open, won or lost: won and lost close the lead.',
            'Keep it short: 4 to 6 open stages work best.',
        ],
        'help' => 'leads',
    ],

    'sources' => [
        'what' => 'Where leads come from: website, walk-in, referral, ads…',
        'why' => 'Reports show which sources bring buyers, so you know where to spend.',
        'steps' => [
            'Add the sources you use. Ads, forms and WhatsApp add their own automatically.',
            'Pick the source when adding a lead by hand.',
            'Compare sources under **Reports → Sources**.',
        ],
        'help' => 'leads',
    ],

    'team' => [
        'what' => 'The people in your workspace and what they can do.',
        'why' => 'New leads are shared between your agents, and each agent sees only their own leads.',
        'steps' => [
            'Use **Invite by email**. You can also copy the invite link and send it on WhatsApp.',
            '**Admins** see everything and change settings; **agents** see only their own leads.',
            'Deactivate someone who leaves: their open leads are handed to others (Autopilot).',
        ],
        'help' => 'team',
    ],

    'import' => [
        'what' => 'Bring in leads from a spreadsheet, or download all your leads.',
        'why' => 'Start with the leads you already have, in minutes.',
        'steps' => [
            'Click **Download template**, paste your leads into it and save it as CSV.',
            'Upload the file. Numbers already in the CRM are skipped, so nothing is duplicated.',
            'Leads are shared between your agents like any new lead.',
        ],
        'help' => 'leads',
    ],

    'activity' => [
        'what' => 'Who did what, and when: sign-ins, lead changes, settings and exports.',
        'why' => 'Answer "who changed this?" and keep your data safe.',
        'steps' => [
            'Filter by person or by kind of action.',
            'Entries are kept for 12 months.',
        ],
        'help' => 'team',
    ],
];
