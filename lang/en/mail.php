<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| E-mail texts
|--------------------------------------------------------------------------
|
| The subject and body of every message the site sends.
|
| On a running install the body comes from the mail_templates table
| (Admin > Mail Templates), which now carries one row per language; these
| strings are the Blade fallback that steps in when that row is missing — the
| template was switched off, or the language was added later. The subject is
| read straight from here whenever no template row answers.
|
| In short: this file is the last line of defence, so that no wording stays
| written into the code. A new language needs a copy under lang/{code}/.
|
*/

return [

    // Mail altbilgisi. Panelden bir metin girilmişse o kazanıyor; bu satır
    // ayar boşken devreye giriyor ve alıcının dilinde yazıyor.
    'footer_text' => 'We are glad to be working with you.',

    'common' => [
        'greeting' => 'Hello',
        'security' => 'Security',
        'date'     => 'Date',
        'email'    => 'E-mail',
        'subject'  => 'Subject',
        'post'     => 'Article',
    ],

    'welcome' => [
        'subject'         => 'Welcome - :site',
        'heading'         => 'Welcome, :name!',
        'lead'            => 'Thank you for joining :site. Welcome aboard! We are happy to have you here.',
        'features'        => 'What you can do with your account',
        'feature_profile' => 'Manage <strong>your profile</strong>',
        'feature_content' => 'Explore <strong>our content</strong>',
        'feature_news'    => 'Hear about <strong>new articles</strong>',
        'feature_contact' => 'Stay <strong>in touch with us</strong>',
        'explore'         => 'Explore the site',
        'outro'           => 'If you have any questions, reach us through our contact page. Have a good day!',
    ],

    'verify' => [
        'subject'  => 'Verify your e-mail address - :site',
        'heading'  => 'Verify your e-mail address',
        'lead'     => ':name, click the button below to verify your e-mail address and start using your account.',
        'button'   => 'Verify my e-mail',
        'fallback' => 'If the button does not work, paste this address into your browser:',
        'ignore'   => 'If you did not create this account you can ignore this e-mail.',
    ],

    'reset' => [
        'subject'  => 'Password reset - :site',
        'heading'  => 'Password reset request',
        'lead'     => 'Hello, we received a password reset request for your account. Click the button below to set a new password:',
        'button'   => 'Reset my password',
        'expires'  => 'This reset link expires in :minutes minutes.',
        'ignore'   => 'If you did not ask for a password reset you can ignore this e-mail. Your account is safe.',
        'fallback' => 'If the button does not work, copy the link below into your browser:',
    ],

    'reset_code' => [
        'subject' => 'Your password reset code - :site',
        'heading' => 'Your password reset code',
        'lead'    => 'Hello, we received a password reset request for your account. Enter the code below in the app:',
        'expires' => 'This code expires in :minutes minutes.',
        'ignore'  => 'If you did not ask for a password reset you can ignore this e-mail. Never share the code with anyone; our team will never ask you for it.',
    ],

    'email_changed' => [
        'subject'     => 'The e-mail address on your account was changed - :site',
        'heading'     => 'The e-mail address on your account was changed',
        'lead'        => 'Hello :name, the e-mail address on your account was changed on :date.',
        'previous'    => 'Previous address',
        'new'         => 'New address',
        'was_you'     => 'If you made this change there is nothing to do; you can ignore this notice.',
        'was_not_you' => '<strong>If you did not make this change, someone else may have taken over your account.</strong> Notifications and password reset links now go to the new address, so you may not be able to recover the account on your own. Contact us straight away:',
        'last_mail'   => 'This message was sent to your old address one last time, before it was removed from the account.',
    ],

    'contact_notification' => [
        'subject' => 'New contact message - :subject',
        'eyebrow' => 'Contact',
        'heading' => 'New contact message',
        'lead'    => 'A new message came in through the website.',
        'from'    => 'From',
        'phone'   => 'Phone',
        'body'    => 'The message',
        'outro'   => 'You can read and answer this message from the admin panel.',
        'button'  => 'Open the message',
    ],

    'contact_reply' => [
        'subject'  => 'Re: :subject',
        'greeting' => 'Hello :name,',
        'heading'  => 'A reply to your message',
        'lead'     => 'Thank you for the message you sent through our contact form. Here is our reply:',
        'original' => 'Your original message',
        'outro'    => 'If you have further questions you can reply to this e-mail or reach us through our website.',
    ],

    'comment_admin' => [
        'subject' => 'New comment: :post - :site',
        'eyebrow' => 'Comment',
        'heading' => 'A new comment arrived',
        'lead'    => 'A new comment was left on an article. It is waiting for approval and stays hidden on the site until then.',
        'author'  => 'Author',
        'body'    => 'The comment',
        'button'  => 'Review the comment',
    ],

    'comment_received' => [
        'subject' => 'We received your comment - :site',
        'heading' => 'We received your comment',
        'lead'    => 'Your comment reached us and is being reviewed. Once it is approved it will appear below the article, and we will let you know.',
        'body'    => 'Your comment',
        'ignore'  => 'If you did not write this comment you can ignore this e-mail.',
    ],

    'comment_approved' => [
        'subject' => 'Your comment is live - :site',
        'heading' => 'Your comment is live',
        'lead'    => 'Your comment has been approved and is now visible to everyone below the article. Thank you for contributing.',
        'body'    => 'Your comment',
    ],

    'campaign' => [
        'test_notice'      => '<strong>This is a test send.</strong> It was not sent to the recipient list.',
        'unsubscribe'      => 'If you no longer want these e-mails you can :link.',
        'unsubscribe_link' => 'unsubscribe',
    ],

    'report' => [
        'subject' => ':title - :site',
        'eyebrow' => 'Report',
        'lead'    => 'Your :frequency report is attached to this e-mail.',
        'range'   => 'Date range',
        'outro'   => 'To stop receiving this report, switch its definition off under Reports → Scheduled Reports in the admin panel.',
    ],

    'test' => [
        'subject' => ':site — test e-mail',
        'eyebrow' => 'Test e-mail',
        'outro'   => 'This message was sent to check whether your SMTP settings work.',
    ],

];
