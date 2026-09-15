<?php

/**
 * Central glossary for all of Bureau's own Dhivehi UI text — NOT
 * Filament's framework chrome (see lang/vendor/filament-panels/dv for
 * that; layout.php there only overrides `direction`, so chrome strings
 * like "Sign out"/"Notifications" still fall back to English for now).
 *
 * Every string in this file is provisional/best-effort — written by an
 * AI without native Dhivehi fluency, not yet reviewed by a native
 * speaker. It exists as ONE file specifically so a review only has to
 * touch this file to correct the module's entire vocabulary, instead
 * of hunting through every page as the module grows across Meeting,
 * Minutes, Decisions, and Votes.
 */
return [

    'module_name' => 'ކައުންސިލްގެ ބައްދަލުވުންތައް',

    'settings_nav_group' => 'ސެޓިންގްސް',

    'roles' => [
        'president' => 'ރައީސް',
        'councillor' => 'ކައުންސިލަރ',
        'participant' => 'ބައިވެރިން',
        'bureau_admin' => 'ބިއުރޯ އެޑްމިން',
        'staff' => 'މުވައްޒަފުން',
        'module_admin' => 'މޮޑިއުލް އެޑްމިން',
    ],

    'roles_page' => [
        'nav_label' => 'ރޯލްތައް',
        'title' => 'ރޯލްތައް',
        'heading' => 'ބިއުރޯ — ބޭނުންކުރާ ފަރާތްތަކުގެ ރޯލްތައް',

        'field' => [
            'name' => 'ނަން',
            'position' => 'މަޤާމް',
            'email' => 'އީމެއިލް',
            'roles' => 'ރޯލްތައް',
        ],

        'placeholder' => [
            'none' => 'ނެތް',
        ],

        'actions' => [
            'edit_roles' => 'ރޯލްތައް ބަދަލުކުރައްވާ',
        ],

        'notifications' => [
            'updated' => 'ރޯލްތައް އަޕްޑޭޓްކުރެވިއްޖެ',
        ],
    ],

    'meeting_settings' => [
        'nav_label' => 'ބައްދަލުވުމުގެ ސެޓިންގްސް',
        'title' => 'ބައްދަލުވުމުގެ ސެޓިންގްސް',

        'field' => [
            'current_term_number' => 'މިހާރުގެ ދައުރު',
            'current_term_number_help' => 'ދައުރު ބަދަލުވަނީ ކޮންމެ 5 އަހަރަކުން — އައު ދައުރެއް ފެށޭއިރު މި ނަންބަރު އިތުރުކުރައްވާ.',
            'next_public_meeting_number' => 'ދެން ބާއްވާ އާއްމު ބައްދަލުވުމުގެ ނަންބަރު',
            'next_private_meeting_number' => 'ދެން ބާއްވާ ކުއްލި ބައްދަލުވުމުގެ ނަންބަރު',
            'next_meeting_number_help' => 'އައު ދައުރެއް ފެށޭއިރު 1 އިން ފަށްޓަވާ. މި ސިސްޓަމް ބޭނުންކުރަން ފެށުމުގެ ކުރިން ބައްދަލުވުންތައް ބާއްވާފައިވާނަމަ، ދެން އޮތް ނަންބަރު މިތާ ޖައްސަވާ.',
            'pdf_header_text' => 'PDF ހެޑަރުގައި ހިމަނާނެ ލިޔުން',
            'pdf_header_text_help' => 'މި ބިއުރޯ މޮޑިއުލުން ދޫކުރާ ހުރިހާ PDF ގެ ކޮންމެ ސަފްޙާއެއްގެ މަތީގައި ފެންނާނެ.',
            'pdf_footer_text' => 'PDF ފުޓަރުގައި ހިމަނާނެ ލިޔުން',
            'pdf_footer_text_help' => 'މި ބިއުރޯ މޮޑިއުލުން ދޫކުރާ ހުރިހާ PDF ގެ ކޮންމެ ސަފްޙާއެއްގެ ދަށުގައި ފެންނާނެ (ސަފްޙާ ނަންބަރާއެކު).',
        ],

        'pdf_section_title' => 'PDF ހެޑަރު/ފުޓަރު',
        'pdf_section_description' => 'ބައްދަލުވުމަށް ދަންނަވާ، ބައްދަލުވުމުގެ އެޖެންޑާ، އަދި ޔައުމިއްޔާ ފަދަ ހުރިހާ PDF އެއްގައި މި ހެޑަރު/ފުޓަރު ބޭނުންކުރެވޭނެ.',

        'actions' => [
            'save' => 'ރައްކާކުރައްވާ',
        ],

        'notifications' => [
            'updated' => 'ސެޓިންގްސް އަޕްޑޭޓްކުރެވިއްޖެ',
        ],
    ],

    'agenda' => [
        'nav_label' => 'އެޖެންޑާ',
        'label' => 'އެޖެންޑާ',
        'plural_label' => 'އެޖެންޑާ',

        'field' => [
            'details' => 'މައްސަލައިގެ ތަފްސީލު',
            'attachment' => 'ލިޔެކިޔުން',
            'status' => 'ސްޓޭޓަސް',
            'kind' => 'ބާވަތް',
            'created_by' => 'ހުށަހެޅި ފަރާތް',
            'created_at' => 'ހުށހެޅި ތާރީޚް',
            'reviewed_by' => 'ބެއްލެވި ފަރާތް',
            'rejection_reason' => 'ރައްދުކުރުމުގެ ސަބަބު',
        ],

        'status' => [
            'entered' => 'ހުށަހެޅިފައި',
            'approved' => 'އެޕްރޫވްކުރެވިފައި',
            'added_to_meeting' => 'ބައްދަލުވުމަށް އެޖެންޑާ ކުރެވިފައި',
            'rejected' => 'ރައްދުކުރެވިފައި',
        ],

        'kind' => [
            'regular' => 'އާދައިގެ',
            'agenda_passing' => 'އެޖެންޑާ ފާސްކުރުން',
            'minutes_passing' => 'ޔައުމިއްޔާ ފާސްކުރުން',
            'agenda_passing_details' => 'މިބައްދަލުވުމުގެ އެޖެންޑާ ފާސްކުރުން',
            'minutes_passing_details' => ':meeting ގެ ޔައުމިއްޔާ ފާސްކުރުން',
        ],

        /**
         * The four sub-headings the meeting's agenda is grouped under
         * on both the View Meeting page and the agenda PDF (see
         * BureauMeeting::groupedAgendaItems()) — passing items first,
         * then regular items bucketed by the creator's bureau role.
         */
        'group' => [
            'passing' => 'އެޖެންޑާ/ޔައުމިއްޔާ ފާސްކުރުމަށް ހުށަހެޅޭ މައްސަލަ',
            'president' => 'ރިޔާސަތުން ހުށަހަޅާ މައްސަލަ',
            'councillor' => 'މެމްބަރުން ހުށަހަޅާ މައްސަލަ',
            'bureau' => 'އިދާރާއިން ހުށަހަޅާ މައްސަލަ',
        ],

        'actions' => [
            'add' => 'އިތުރުކުރައްވާ',
            'edit' => 'ބަދަލުކުރައްވާ',
            'approve' => 'ފާސްކުރައްވާ',
            'reject' => 'ރައްދުކުރައްވާ',
            'save' => 'ރައްކާކުރައްވާ',
            'cancel' => 'ކެންސަލްކުރައްވާ',
            'delete' => 'ފޮހެލައްވާ',
        ],
    ],

    'meeting' => [
        'nav_label' => 'ބައްދަލުވުން',
        'label' => 'ބައްދަލުވުން',
        'plural_label' => 'ބައްދަލުވުންތައް',

        'field' => [
            'name' => 'ނަން',
            'type' => 'ބާވަތް',
            'scheduled_at' => 'ތާރީޚާއި ގަޑި',
            'scheduled_day' => 'ދުވަސް',
            'scheduled_month' => 'މަސް',
            'scheduled_year' => 'އަހަރު',
            'scheduled_time' => 'ގަޑި',
            'scheduled_day_invalid' => 'ޚިޔާރުކުރެއްވި މަހުގައި މިހާ ގިނަ ދުވަސް ނުހުރޭ.',
            'place' => 'ތަން',
            'status' => 'ހާލަތު',
            'attendees' => 'ބައިވެރިން',
            'attendees_heading' => 'ބައްދަލުވުމަށް ދެންނެވިފައިވާ ފަރާތްތައް',
            'agenda_items' => 'އެޖެންޑާ',
            'attachments' => 'ލިޔެކިޔުންތައް',
            'agenda_items_help' => 'ފާސްކުރެވިފައިވާ ހުރިހާ މައްސަލައެއް ޑީފޯލްޓްކޮށް ނިސާވެފައި — ބޭނުންފުޅުނުވާ މައްސަލައެއް ނިސާވުމުން ނަންގަވާ.',
            'include_agenda_passing' => 'އެޖެންޑާ ފާސްކުރުން އިތުރުކުރައްވާ',
            'minutes_passing_meetings' => 'ޔައުމިއްޔާ ފާސްކުރުން',
            'minutes_passing_meetings_help' => 'ޑީފޯލްޓްކޮށް ނިސާވެފައި ވަނީ އެންމެފަހުން ބޭއްވި ބައްދަލުވުން — އިތުރު ބައްދަލުވުމެއްގެ ޔައުމިއްޔާ ފާސްކުރަން ބޭނުންފުޅުނަމަ އިތުރުކުރައްވާ.',
            'created_by' => 'ތައްޔާރުކުރި ފަރާތް',
            'reviewed_by' => 'ބެއްލެވި ފަރާތް',
            'rejection_reason' => 'ރައްދުކުރުމުގެ ސަބަބު',
            'meeting_request_pdf' => 'ބައްދަލުވުމަށް ދަންނަވާ',
            'agenda_pdf' => 'ބައްދަލުވުމުގެ އެޖެންޑާ',
            'participant_name' => 'ނަން',
            'participant_email' => 'އީމެއިލް',
            'participant_position' => 'މަޤާމް',
        ],

        'status' => [
            'draft' => 'ޑްރާފްޓް',
            'pending_approval' => 'ތައްޔާރުކުރެވިފައި',
            'scheduled' => 'ތާވަލުކުރެވިފައި',
            'rejected' => 'ރައްދުކުރެވިފައި',
        ],

        'actions' => [
            'add' => 'ބައްދަލުވުމަށް ދަންނަވާ',
            'edit' => 'ބަދަލުކުރައްވާ',
            'cancel' => 'ކެންސަލްކުރައްވާ',
            'send_for_approval' => 'ފާސްކުރުމަށް ފޮނުއްވާ',
            'approve' => 'ފާސްކުރައްވާ',
            'reject' => 'ރައްދުކުރައްވާ',
            'save' => 'ސޭވްކުރައްވާ',
            'delete' => 'ފޮހެލައްވާ',
        ],

        'dashboard' => [
            'next_meeting' => 'އަންނަން އޮތް ބައްދަލުވުން',
            'no_upcoming_meeting' => 'އަންނަން އޮތް ބައްދަލުވުމެއް ނެތް',
            'countdown_days' => 'ދުވަސް',
            'countdown_hours' => 'ގަޑިއިރު',
            'countdown_minutes' => 'މިނިޓު',
            'countdown_seconds' => 'ސިކުންތު',
            'countdown_started' => 'ބައްދަލުވުން ފަށައިފި',
        ],

        'pdf' => [
            'title' => 'އެޖެންޑާ',
            'attendees_heading' => 'ބައިވެރިން',
            'agenda_heading' => 'އެޖެންޑާ އައިޓަމްތައް',
            'request_title' => 'ބައްދަލުވުމަށް ދަންނަވާ',
            'request_body' => 'ތިރީގައިވާ މަޢުލޫމާތުގެ މައްޗަށް ބައްދަލުވުން ބޭއްވުމަށް ހުއްދަދެއްވުން އެދި ދަންނަވަން.',
        ],
    ],

    'minutes' => [
        'nav_label' => 'ޔައުމިއްޔާ',
        'label' => 'ޔައުމިއްޔާ',

        'status' => [
            'draft' => 'ލިޔަމުންދަނީ',
            'review' => 'މުރާޖަޢާގައި',
            'approved' => 'ފާސްވެފައި',
            'signed' => 'ސޮއިކުރެވިފައި',
        ],

        'decision_status' => [
            'pending' => 'ވޯޓަށް އަހާފައިނުވޭ',
            'passed' => 'ފާސްވެފައި',
            'failed' => 'ފެއިލްވެފައި',
            'skipped' => 'ދޫކޮށްލެވިފައި',
        ],

        'vote' => [
            'yes' => 'ބޭނުން',
            'no' => 'ބޭނުމެއްނޫން',
        ],

        'field' => [
            'started_at' => 'ފެށި ގަޑި',
            'ended_at' => 'ނިމުނު ގަޑި',
            'attendance' => 'ހާޒިރީ',
            'introduction' => 'ތަޢާރަފް',
            'closing_notes' => 'ބައްދަލުވުން ނިންމުމުގެ ނޯޓު',
            'audio' => 'އޯޑިއޯ ރެކޯޑިންގ',
            'speaker' => 'ވާހަކަދެއްކެވި ފަރާތް',
            'comment_text' => 'ދެއްކެވި ވާހަކަ',
            'bullet_points' => 'ނުކުތާތައް',
            'ai_points' => 'AI އަށް ދޭ ނުކުތާތައް (ވަގުތީ)',
            'drafted_text' => 'ޔައުމިއްޔާގެ ލިޔުން',
            'decision_text' => 'ނިންމުމަށް އެދޭ ކަންތައް',
            'reviewed_by' => 'ފާސްކުރެއްވި ފަރާތް',

            // New fields for the card-based recording form.
            'chaired_by' => 'ރިޔާސަތު ބެލެހެއްޓި ފަރާތް',
            'start_date' => 'ތާރީޚް',
            'start_time' => 'ފެށުނު ގަޑި',
            'break_started_at' => 'މެދުކަނޑާލި ގަޑި',
            'break_ended_at' => 'މެދުކަނޑާލުމަށްފަހު ފެށި ގަޑި',
            'listeners' => 'އަޑުއެހުމަށް ހާޟިރުވި ފަރާތްތައް',
            'listener_name' => 'ނަން',
            'listener_address' => 'އެޑްރެސް',
            'decision_requests_heading' => 'ހުށަހެޅުންތައް',
            'proposed_by' => 'ހުށަހެޅި ފަރާތް',
            'vote_total' => 'ޖުމްލަ',
            'meeting_info_heading' => 'ބައްދަލުވުމުގެ މައުލޫމާތު',
            'attendance_card_heading' => 'ބައްދަލުވުމުގެ ހާޟިރީ',
            'discussions_heading' => 'ބައްދަލުވުމުގައި މަޝްވަރާކޮށް ކަންކަމުގެ ތަފްސީލު',
            'closing_card_heading' => 'ބައްދަލުވުން ނިންމުން',
        ],

        'attendance_group' => [
            'council' => 'ހާޟިރުވި ފަރާތްތައް',
            'secretariat' => 'ސެކްރެޓޭރިއެޓް',
        ],

        'attendance_status' => [
            'present' => 'ހާޟިރު',
            'absent' => 'ޣައިރު ހާޟިރު',
            'on_leave' => 'ޗުއްޓީގައި',
        ],

        // Shown on a decision request once a sibling request for the
        // same agenda item has already passed (see
        // DecisionVotingService's auto-skip and RecordMinutes::createDecisionRequest()'s guard).
        'decision_locked' => 'މި މައްސަލައަށް ވަނީ ނިންމުމެއް ނިންމިފައި',

        // Shown when the same speaker tries to raise a second decision
        // request on the same agenda item (see
        // RecordMinutes::createDecisionRequest()'s guard).
        'decision_already_proposed' => 'މި ފަރާތުން މިހާރުވެސް މިމައްސަލައަށް ހުށަހެޅުމެއް ހުށަހަޅުއްވާފައިވޭ',

        'actions' => [
            'start' => 'ޔައުމިއްޔާ ފެއްޓަވާ',
            'save_attendance' => 'ހާޒިރީ ރައްކާކުރައްވާ',
            'save_meeting_info' => 'ބައްދަލުވުމުގެ މައުލޫމާތު ރައްކާކުރައްވާ',
            'save_introduction' => 'ތަޢާރަފް ރައްކާކުރައްވާ',
            'save_closing_notes' => 'ނިންމުމުގެ ނޯޓު ރައްކާކުރައްވާ',
            'add_comment' => 'ބަހެއް އިތުރުކުރައްވާ',
            'draft_with_ai' => 'AI މެދުވެރިކޮށް ލިޔުއްވާ',
            'generate_ai_text' => 'ލިޔުން އުފައްދާ',
            'generate_ai_text_prompt' => 'ލިޔުން އުފެއްދުމަށް',
            'cancel_ai_text' => 'ކެންސަލް',
            'save_comment' => 'ސޭވް',
            'edit_comment' => 'ބަދަލުކުރައްވާ',
            'cancel_edit_comment' => 'ކެންސަލް',
            'cancel_add_comment' => 'ކެންސަލް',
            'add_decision' => 'ނިންމުމަށް އެދޭ ކަމެއް އިތުރުކުރައްވާ',
            'record_votes' => 'ވޯޓު ނަންގަވާ',
            'edit_votes' => 'ވޯޓު އިސްލާޙުކުރައްވާ',
            'end' => 'ޔައުމިއްޔާ ނިންމަވާ',
            'send_for_review' => 'މުރާޖަޢާއަށް ފޮނުއްވާ',
            'approve' => 'ފާސްކުރައްވާ',
            'start_recording' => 'ރެކޯޑިންގ ފެއްޓަވާ',
            'stop_recording' => 'ރެކޯޑިންގ ހުއްޓަވާ',
        ],

        'pdf' => [
            'title' => 'ބައްދަލުވުމުގެ ޔައުމިއްޔާ',
            'introduction_heading' => 'ތަޢާރަފް',
            'attendance_heading' => 'ހާޒިރީ',
            'closing_heading' => 'ނިންމުން',
        ],
    ],

    'mail' => [
        'meeting_scheduled' => [
            'subject' => 'ބައްދަލުވުމުގެ ދަޢުވަތު',
            'greeting' => 'السلام عليكم',
            'body' => 'ތިރީގައިވާ ބައްދަލުވުމަށް ތިޔަ ފަރާތް ދަޢުވަތު އަރުވަން.',
        ],
    ],

];
