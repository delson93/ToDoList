<?php

return [
    'verification' => env('GOOGLE_SITE_VERIFICATION'),
    'analytics_script' => env('PUBLIC_ANALYTICS_SCRIPT'),
    'contact' => env('PUBLIC_CONTACT_EMAIL', 'support@utilynk.com'),
    'pages' => [
        '/' => ['Create and Share Shopping Lists for Free', 'Create shopping lists, grocery lists, and checklists. Share instantly, collaborate, and access your lists offline.', [
            'How it works' => 'Create a list, add what you need, and share the invitation with anyone helping. At the store, tap each item as you go. Completed items move below the things you still need.',
            'Share the work' => 'Send a link by text or WhatsApp. Choose whether the recipient can view, check off, or edit items. Anyone with a valid invitation can join without an account.',
            'Take your list offline' => 'Open the list while you have a connection. Once it says Available offline, you can reopen it and make changes on that device. Your changes sync when you reconnect.',
        ]],
        '/shopping-list' => ['Free Online Shopping List', 'Make a shopping list online, share it without registration, and check off purchases from your phone.', [
            'Get ready before you leave' => 'Start with the items you know you need. Add a quantity or a photo when the label matters. Use alternatives to give the shopper a backup if the first choice is sold out.',
            'Shop with one hand' => 'Shopping Mode gives each item a larger tap area and keeps unfinished items together. Mark an item unavailable so you can find it later without confusing it with a purchase.',
        ]],
        '/grocery-list' => ['Online Grocery List Maker', 'Plan your grocery run, track quantities, and share a grocery list everyone can update.', [
            'Plan the next grocery trip' => 'Start with the groceries you buy often, then check your pantry. Add the amount and unit for produce, pantry staples, and ingredients used in several meals.',
            'Track what you found' => 'If you need five kilos of rice and find three, record the purchased amount. The list shows what remains. Record estimated and actual prices to check spending as you go.',
        ]],
        '/todo-list' => ['Free Online To-Do List', 'Make a simple to-do list with optional priorities, due dates, and assignments.', [
            'Make each task actionable' => 'Write tasks as actions, such as “Book the appointment” or “Send the receipts.” Give a task a due date when it has a deadline and add notes only when you need them.',
            'Coordinate small projects' => 'Invite the people working with you, assign tasks, and let each person check off their work. Keep the completed section visible when you need a record of what got done.',
        ]],
        '/packing-list' => ['Packing List Maker', 'Build a packing checklist for a vacation, work trip, or family outing and take it offline.', [
            'Pack in stages' => 'Start with the travel template and add items for the weather, activities, and length of the trip. Keep last-minute items like chargers and travel documents pending until they are packed.',
            'Coordinate shared gear' => 'Assign things like a first-aid kit or an adapter to one traveler. Open the list before leaving home so it is ready to use in transit.',
        ]],
        '/shared-shopping-list' => ['Shared Shopping List', 'Share a shopping list by link or QR code and give each shopper the access they need.', [
            'Choose the right access' => 'Use view-only access to share a plan. Give shoppers completion access when you want them to update progress. Choose edit access for someone helping decide what to buy.',
            'Keep everyone up to date' => 'Connected participants see updates while they have the list open. If someone goes offline, their device keeps its changes and syncs when it reconnects.',
        ]],
        '/online-checklist' => ['Free Online Checklist Maker', 'Create reusable checklists for events, routines, and projects with no account required.', [
            'Make repeat work easier' => 'Use a checklist for room setup, event supplies, or a closing routine. Make each checkbox represent one action so anyone can see what remains.',
            'Schedule another copy' => 'Set a weekly, monthly, or custom schedule when the same list comes up regularly. Keep the original as your starting point and use the generated copy for the next run.',
        ]],
        '/features' => ['Utilynk Lists Features', 'Explore offline checklists, Shopping Mode, private sharing, photos, assignments, and list templates.', [
            'Everyday list tools' => 'Add an item by typing its name. Expand item details for quantities, alternatives, prices, due dates, photos, or assignments. Use Shopping Mode for larger controls in the store.',
            'Collaboration and reuse' => 'Use invitations with different access levels, discuss items, or assign a shopper. Start with a grocery, travel, task, or event template and schedule recurring copies when needed.',
            'Home screen access' => 'Install the app from a supporting browser or use the browser’s home-screen shortcut option. Use My lists to reopen lists saved for offline use.',
        ]],
        '/about' => ['About Utilynk Lists', 'Learn why Utilynk Lists keeps shared errands and checklists simple and available offline.', [
            'Built for everyday coordination' => 'Utilynk Lists helps households and small groups coordinate errands, travel, and routine work. You can create a list and share it before creating an account.',
            'Designed for interruptions' => 'A weak connection is common in stores and during travel. Open a list before you leave and let the app finish saving it for offline use.',
        ]],
        '/contact' => ['Contact Utilynk Lists', 'Get help with your Utilynk Lists account, offline access, or an invitation.', [
            'What to include' => 'Tell us your browser and device, what you were trying to do, and what happened. For a sync issue, include the status message shown on your list.',
            'Keep list links private' => 'Use the contact email below. Leave invitation links, passwords, and private list contents out of your message.',
        ]],
        '/privacy-policy' => ['Utilynk Lists Privacy Policy', 'Learn how Utilynk Lists handles guest access, account details, invitations, and offline copies.', [
            'Information needed to run the service' => 'The service stores your list contents, participation, and activity so participants can collaborate. Guest access uses a browser credential. Accounts use your name, email address, and a password hash or configured sign-in provider.',
            'Sharing and device storage' => 'Invitations grant access with the selected permission. Participants can keep local copies on their device for offline use. Removing a participant stops future server access. A device that is offline may still have a copy until it reconnects or its site data is cleared.',
            'Questions about your data' => 'Use the contact page to ask about access or deletion. Include the email associated with your account if you have one.',
        ]],
        '/terms' => ['Utilynk Lists Terms of Use', 'Read the terms for creating, sharing, and using lists with Utilynk Lists.', [
            'Use of the service' => 'Use lists for lawful purposes and upload or share content you have permission to use. Respect the privacy and access choices of other participants.',
            'Invitations and access' => 'Share invitation links with the people you want to join. Choose an appropriate permission and revoke unused links. Keep your devices and account credentials secure.',
            'Availability' => 'Connection, browser storage, and server availability can affect access. Keep your own copy of information that you cannot afford to lose.',
        ]],
    ],
];
