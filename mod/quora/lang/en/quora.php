<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'quora', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package   mod_quora
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activityoverview'] = 'There are new quora posts';
$string['addanewdiscussion'] = 'Add a new discussion topic';
$string['addanewquestion'] = 'Add a new question';
$string['addanewtopic'] = 'Add a new topic';
$string['advancedsearch'] = 'Advanced search';
$string['allquoras'] = 'All quoras';
$string['allowdiscussions'] = 'Can a {$a} post to this quora?';
$string['allowsallsubscribe'] = 'This quora allows everyone to choose whether to subscribe or not';
$string['allowsdiscussions'] = 'This quora allows each person to start one discussion topic.';
$string['allsubscribe'] = 'Subscribe to all quoras';
$string['allunsubscribe'] = 'Unsubscribe from all quoras';
$string['alreadyfirstpost'] = 'This is already the first post in the discussion';
$string['anyfile'] = 'Any file';
$string['areaattachment'] = 'Attachments';
$string['areapost'] = 'Messages';
$string['attachment'] = 'Attachment';
$string['attachment_help'] = 'You can optionally attach one or more files to a quora post. If you attach an image, it will be displayed after the message.';
$string['attachmentnopost'] = 'You cannot export attachments without a post id';
$string['attachments'] = 'Attachments';
$string['attachmentswordcount'] = 'Attachments and word count';
$string['blockafter'] = 'Post threshold for blocking';
$string['blockafter_help'] = 'This setting specifies the maximum number of posts which a user can post in the given time period. Users with the capability mod/quora:postwithoutthrottling are exempt from post limits.';
$string['blockperiod'] = 'Time period for blocking';
$string['blockperiod_help'] = 'Students can be blocked from posting more than a given number of posts in a given time period. Users with the capability mod/quora:postwithoutthrottling are exempt from post limits.';
$string['blockperioddisabled'] = 'Don\'t block';
$string['blogquora'] = 'Standard quora displayed in a blog-like format';
$string['bynameondate'] = 'by {$a->name} - {$a->date}';
$string['cannotadd'] = 'Could not add the discussion for this quora';
$string['cannotadddiscussion'] = 'Adding discussions to this quora requires group membership.';
$string['cannotadddiscussionall'] = 'You do not have permission to add a new discussion topic for all participants.';
$string['cannotaddsubscriber'] = 'Could not add subscriber with id {$a} to this quora!';
$string['cannotaddteacherquorato'] = 'Could not add converted teacher quora instance to section 0 in the course';
$string['cannotcreatediscussion'] = 'Could not create new discussion';
$string['cannotcreateinstanceforteacher'] = 'Could not create new course module instance for the teacher quora';
$string['cannotdeletepost'] = 'You can\'t delete this post!';
$string['cannoteditposts'] = 'You can\'t edit other people\'s posts!';
$string['cannotfinddiscussion'] = 'Could not find the discussion in this quora';
$string['cannotfindfirstpost'] = 'Could not find the first post in this quora';
$string['cannotfindorcreatequora'] = 'Could not find or create a main news quora for the site';
$string['cannotfindparentpost'] = 'Could not find top parent of post {$a}';
$string['cannotmovefromsinglequora'] = 'Cannot move discussion from a simple single discussion quora';
$string['cannotmovenotvisible'] = 'Forum not visible';
$string['cannotmovetonotexist'] = 'You can\'t move to that quora - it doesn\'t exist!';
$string['cannotmovetonotfound'] = 'Target quora not found in this course.';
$string['cannotmovetosinglequora'] = 'Cannot move discussion to a simple single discussion quora';
$string['cannotpurgecachedrss'] = 'Could not purge the cached RSS feeds for the source and/or destination quora(s) - check your file permissionsquoras';
$string['cannotremovesubscriber'] = 'Could not remove subscriber with id {$a} from this quora!';
$string['cannotreply'] = 'You cannot reply to this post';
$string['cannotsplit'] = 'Discussions from this quora cannot be split';
$string['cannotsubscribe'] = 'Sorry, but you must be a group member to subscribe.';
$string['cannottrack'] = 'Could not stop tracking that quora';
$string['cannotunsubscribe'] = 'Could not unsubscribe you from that quora';
$string['cannotupdatepost'] = 'You can not update this post';
$string['cannotviewpostyet'] = 'You cannot read other students questions in this discussion yet because you haven\'t posted';
$string['cannotviewusersposts'] = 'There are no posts made by this user that you are able to view.';
$string['cleanreadtime'] = 'Mark old posts as read hour';
$string['clicktounsubscribe'] = 'You are subscribed to this discussion. Click to unsubscribe.';
$string['clicktosubscribe'] = 'You are not subscribed to this discussion. Click to subscribe.';
$string['completiondiscussions'] = 'Student must create discussions:';
$string['completiondiscussionsgroup'] = 'Require discussions';
$string['completiondiscussionshelp'] = 'requiring discussions to complete';
$string['completionposts'] = 'Student must post discussions or replies:';
$string['completionpostsgroup'] = 'Require posts';
$string['completionpostshelp'] = 'requiring discussions or replies to complete';
$string['completionreplies'] = 'Student must post replies:';
$string['completionrepliesgroup'] = 'Require replies';
$string['completionreplieshelp'] = 'requiring replies to complete';
$string['configcleanreadtime'] = 'The hour of the day to clean old posts from the \'read\' table.';
$string['configdigestmailtime'] = 'People who choose to have emails sent to them in digest form will be emailed the digest daily. This setting controls which time of day the daily mail will be sent (the next cron that runs after this hour will send it).';
$string['configdisplaymode'] = 'The default display mode for discussions if one isn\'t set.';
$string['configenablerssfeeds'] = 'This switch will enable the possibility of RSS feeds for all quoras.  You will still need to turn feeds on manually in the settings for each quora.';
$string['configenabletimedposts'] = 'Set to \'yes\' if you want to allow setting of display periods when posting a new quora discussion (Experimental as not yet fully tested)';
$string['configlongpost'] = 'Any post over this length (in characters not including HTML) is considered long. Posts displayed on the site front page, social format course pages, or user profiles are shortened to a natural break somewhere between the quora_shortpost and quora_longpost values.';
$string['configmanydiscussions'] = 'Maximum number of discussions shown in a quora per page';
$string['configmaxattachments'] = 'Default maximum number of attachments allowed per post.';
$string['configmaxbytes'] = 'Default maximum size for all quora attachments on the site (subject to course limits and other local settings)';
$string['configoldpostdays'] = 'Number of days old any post is considered read.';
$string['configreplytouser'] = 'When a quora post is mailed out, should it contain the user\'s email address so that recipients can reply personally rather than via the quora? Even if set to \'Yes\' users can choose in their profile to keep their email address secret.';
$string['configrsstypedefault'] = 'If RSS feeds are enabled, sets the default activity type.';
$string['configrssarticlesdefault'] = 'If RSS feeds are enabled, sets the default number of articles (either discussions or posts).';
$string['configshortpost'] = 'Any post under this length (in characters not including HTML) is considered short (see below).';
$string['configtrackingtype'] = 'Default setting for read tracking.';
$string['configtrackreadposts'] = 'Set to \'yes\' if you want to track read/unread for each user.';
$string['configusermarksread'] = 'If \'yes\', the user must manually mark a post as read. If \'no\', when the post is viewed it is marked as read.';
$string['confirmsubscribediscussion'] = 'Do you really want to subscribe to discussion \'{$a->discussion}\' in quora \'{$a->quora}\'?';
$string['confirmunsubscribediscussion'] = 'Do you really want to unsubscribe from discussion \'{$a->discussion}\' in quora \'{$a->quora}\'?';
$string['confirmsubscribe'] = 'Do you really want to subscribe to quora \'{$a}\'?';
$string['confirmunsubscribe'] = 'Do you really want to unsubscribe from quora \'{$a}\'?';
$string['couldnotadd'] = 'Could not add your post due to an unknown error';
$string['couldnotdeletereplies'] = 'Sorry, that cannot be deleted as people have already responded to it';
$string['couldnotupdate'] = 'Could not update your post due to an unknown error';
$string['crontask'] = 'Forum mailings and maintenance jobs';
$string['delete'] = 'Delete';
$string['deleteddiscussion'] = 'The discussion topic has been deleted';
$string['deletedpost'] = 'The post has been deleted';
$string['deletedposts'] = 'Those posts have been deleted';
$string['deletesure'] = 'Are you sure you want to delete this post?';
$string['deletesureplural'] = 'Are you sure you want to delete this post and all replies? ({$a} posts)';
$string['digestmailheader'] = 'This is your daily digest of new posts from the {$a->sitename} quoras. To change your default quora email preferences, go to {$a->userprefs}.';
$string['digestmailpost'] = 'Change your quora digest preferences';
$string['digestmailprefs'] = 'your user profile';
$string['digestmailsubject'] = '{$a}: quora digest';
$string['digestmailtime'] = 'Hour to send digest emails';
$string['digestsentusers'] = 'Email digests successfully sent to {$a} users.';
$string['disallowsubscribe'] = 'Subscriptions not allowed';
$string['disallowsubscription'] = 'Subscription';
$string['disallowsubscription_help'] = 'This quora has been configured so that you cannot subscribe to discussions.';
$string['disallowsubscribeteacher'] = 'Subscriptions not allowed (except for teachers)';
$string['discussion'] = 'Discussion';
$string['discussionmoved'] = 'This discussion has been moved to \'{$a}\'.';
$string['discussionmovedpost'] = 'This discussion has been moved to <a href="{$a->discusshref}">here</a> in the quora <a href="{$a->quorahref}">{$a->quoraname}</a>';
$string['discussionname'] = 'Discussion name';
$string['discussionnownotsubscribed'] = '{$a->name} will NOT be notified of new posts in \'{$a->discussion}\' of \'{$a->quora}\'';
$string['discussionnowsubscribed'] = '{$a->name} will be notified of new posts in \'{$a->discussion}\' of \'{$a->quora}\'';
$string['discussionsubscribestop'] = 'I don\'t want to be notified of new posts in this discussion';
$string['discussionsubscribestart'] = 'Send me notifications of new posts in this discussion';
$string['discussionsubscription'] = 'Discussion subscription';
$string['discussionsubscription_help'] = 'Subscribing to a discussion means you will receive notifications of new posts to that discussion.';
$string['discussions'] = 'Discussions';
$string['discussionsstartedby'] = 'Discussions started by {$a}';
$string['discussionsstartedbyrecent'] = 'Discussions recently started by {$a}';
$string['discussionsstartedbyuserincourse'] = 'Discussions started by {$a->fullname} in {$a->coursename}';
$string['discussthistopic'] = 'Discuss this topic';
$string['displayend'] = 'Display end';
$string['displayend_help'] = 'This setting specifies whether a quora post should be hidden after a certain date. Note that administrators can always view quora posts.';
$string['displaymode'] = 'Display mode';
$string['displayperiod'] = 'Display period';
$string['displaystart'] = 'Display start';
$string['displaystart_help'] = 'This setting specifies whether a quora post should be displayed from a certain date. Note that administrators can always view quora posts.';
$string['displaywordcount'] = 'Display word count';
$string['displaywordcount_help'] = 'This setting specifies whether the word count of each post should be displayed or not.';
$string['eachuserquora'] = 'Each person posts one discussion';
$string['edit'] = 'Edit';
$string['editedby'] = 'Edited by {$a->name} - original submission {$a->date}';
$string['editedpostupdated'] = '{$a}\'s post was updated';
$string['editing'] = 'Editing';
$string['eventcoursesearched'] = 'Course searched';
$string['eventdiscussioncreated'] = 'Discussion created';
$string['eventdiscussionupdated'] = 'Discussion updated';
$string['eventdiscussiondeleted'] = 'Discussion deleted';
$string['eventdiscussionmoved'] = 'Discussion moved';
$string['eventdiscussionviewed'] = 'Discussion viewed';
$string['eventdiscussionsubscriptioncreated'] = 'Discussion subscription created';
$string['eventdiscussionsubscriptiondeleted'] = 'Discussion subscription deleted';
$string['eventuserreportviewed'] = 'User report viewed';
$string['eventpostcreated'] = 'Post created';
$string['eventpostdeleted'] = 'Post deleted';
$string['eventpostupdated'] = 'Post updated';
$string['eventreadtrackingdisabled'] = 'Read tracking disabled';
$string['eventreadtrackingenabled'] = 'Read tracking enabled';
$string['eventsubscribersviewed'] = 'Subscribers viewed';
$string['eventsubscriptioncreated'] = 'Subscription created';
$string['eventsubscriptiondeleted'] = 'Subscription deleted';
$string['emaildigestcompleteshort'] = 'Complete posts';
$string['emaildigestdefault'] = 'Default ({$a})';
$string['emaildigestoffshort'] = 'No digest';
$string['emaildigestsubjectsshort'] = 'Subjects only';
$string['emaildigesttype'] = 'Email digest options';
$string['emaildigesttype_help'] = 'The type of notification that you will receive for each quora.

* Default - follow the digest setting found in your user profile. If you update your profile, then that change will be reflected here too;
* No digest - you will receive one e-mail per quora post;
* Digest - complete posts - you will receive one digest e-mail per day containing the complete contents of each quora post;
* Digest - subjects only - you will receive one digest e-mail per day containing just the subject of each quora post.
';
$string['emaildigestupdated'] = 'The e-mail digest option was changed to \'{$a->maildigesttitle}\' for the quora \'{$a->quora}\'. {$a->maildigestdescription}';
$string['emaildigestupdated_default'] = 'Your default profile setting of \'{$a->maildigesttitle}\' was used for the quora \'{$a->quora}\'. {$a->maildigestdescription}.';
$string['emaildigest_0'] = 'You will receive one e-mail per quora post.';
$string['emaildigest_1'] = 'You will receive one digest e-mail per day containing the complete contents of each quora post.';
$string['emaildigest_2'] = 'You will receive one digest e-mail per day containing the subject of each quora post.';
$string['emptymessage'] = 'Something was wrong with your post. Perhaps you left it blank, or the attachment was too big. Your changes have NOT been saved.';
$string['erroremptymessage'] = 'Post message cannot be empty';
$string['erroremptysubject'] = 'Post subject cannot be empty.';
$string['errorenrolmentrequired'] = 'You must be enrolled in this course to access this content';
$string['errorwhiledelete'] = 'An error occurred while deleting record.';
$string['eventassessableuploaded'] = 'Some content has been posted.';
$string['everyonecanchoose'] = 'Everyone can choose to be subscribed';
$string['everyonecannowchoose'] = 'Everyone can now choose to be subscribed';
$string['everyoneisnowsubscribed'] = 'Everyone is now subscribed to this quora';
$string['everyoneissubscribed'] = 'Everyone is subscribed to this quora';
$string['existingsubscribers'] = 'Existing subscribers';
$string['exportdiscussion'] = 'Export whole discussion to portfolio';
$string['forcedreadtracking'] = 'Allow forced read tracking';
$string['forcedreadtracking_desc'] = 'Allows quoras to be set to forced read tracking. Will result in decreased performance for some users, particularly on courses with many quoras and posts. When off, any quoras previously set to Forced are treated as optional.';
$string['forcesubscribed_help'] = 'This quora has been configured so that you cannot unsubscribe from discussions.';
$string['forcesubscribed'] = 'This quora forces everyone to be subscribed';
$string['quora'] = 'Forum';
$string['quora:addinstance'] = 'Add a new quora';
$string['quora:addnews'] = 'Add news';
$string['quora:addquestion'] = 'Add question';
$string['quora:allowforcesubscribe'] = 'Allow force subscribe';
$string['quoraauthorhidden'] = 'Author (hidden)';
$string['quorablockingalmosttoomanyposts'] = 'You are approaching the posting threshold. You have posted {$a->numposts} times in the last {$a->blockperiod} and the limit is {$a->blockafter} posts.';
$string['quorabodyhidden'] = 'This post cannot be viewed by you, probably because you have not posted in the discussion, the maximum editing time hasn\'t passed yet, the discussion has not started or the discussion has expired.';
$string['quora:canposttomygroups'] = 'Can post to all groups you have access to';
$string['quora:createattachment'] = 'Create attachments';
$string['quora:deleteanypost'] = 'Delete any posts (anytime)';
$string['quora:deleteownpost'] = 'Delete own posts (within deadline)';
$string['quora:editanypost'] = 'Edit any post';
$string['quora:exportdiscussion'] = 'Export whole discussion';
$string['quora:exportownpost'] = 'Export own post';
$string['quora:exportpost'] = 'Export post';
$string['quoraintro'] = 'Description';
$string['quora:managesubscriptions'] = 'Manage subscriptions';
$string['quora:movediscussions'] = 'Move discussions';
$string['quora:postwithoutthrottling'] = 'Exempt from post threshold';
$string['quoraname'] = 'Forum name';
$string['quoraposts'] = 'Forum posts';
$string['quora:rate'] = 'Rate posts';
$string['quora:replynews'] = 'Reply to news';
$string['quora:replypost'] = 'Reply to posts';
$string['quoras'] = 'Forums';
$string['quora:splitdiscussions'] = 'Split discussions';
$string['quora:startdiscussion'] = 'Start new discussions';
$string['quorasubjecthidden'] = 'Subject (hidden)';
$string['quoratracked'] = 'Unread posts are being tracked';
$string['quoratrackednot'] = 'Unread posts are not being tracked';
$string['quoratype'] = 'Forum type';
$string['quoratype_help'] = 'There are 5 quora types:

* A single simple discussion - A single discussion topic which everyone can reply to (cannot be used with separate groups)
* Each person posts one discussion - Each student can post exactly one new discussion topic, which everyone can then reply to
* Q and A quora - Students must first post their perspectives before viewing other students\' posts
* Standard quora displayed in a blog-like format - An open quora where anyone can start a new discussion at any time, and in which discussion topics are displayed on one page with "Discuss this topic" links
* Standard quora for general use - An open quora where anyone can start a new discussion at any time';
$string['quora:viewallratings'] = 'View all raw ratings given by individuals';
$string['quora:viewanyrating'] = 'View total ratings that anyone received';
$string['quora:viewdiscussion'] = 'View discussions';
$string['quora:viewhiddentimedposts'] = 'View hidden timed posts';
$string['quora:viewqandawithoutposting'] = 'Always see Q and A posts';
$string['quora:viewrating'] = 'View the total rating you received';
$string['quora:viewsubscribers'] = 'View subscribers';
$string['generalquora'] = 'Standard quora for general use';
$string['generalquoras'] = 'General quoras';
$string['hiddenquorapost'] = 'Hidden quora post';
$string['inquora'] = 'in {$a}';
$string['introblog'] = 'The posts in this quora were copied here automatically from blogs of users in this course because those blog entries are no longer available';
$string['intronews'] = 'General news and announcements';
$string['introsocial'] = 'An open quora for chatting about anything you want to';
$string['introteacher'] = 'A quora for teacher-only notes and discussion';
$string['invalidaccess'] = 'This page was not accessed correctly';
$string['invaliddiscussionid'] = 'Discussion ID was incorrect or no longer exists';
$string['invaliddigestsetting'] = 'An invalid mail digest setting was provided';
$string['invalidforcesubscribe'] = 'Invalid force subscription mode';
$string['invalidquoraid'] = 'Forum ID was incorrect';
$string['invalidparentpostid'] = 'Parent post ID was incorrect';
$string['invalidpostid'] = 'Invalid post ID - {$a}';
$string['lastpost'] = 'Last post';
$string['learningquoras'] = 'Learning quoras';
$string['longpost'] = 'Long post';
$string['mailnow'] = 'Mail now';
$string['manydiscussions'] = 'Discussions per page';
$string['markalldread'] = 'Mark all posts in this discussion read.';
$string['markallread'] = 'Mark all posts in this quora read.';
$string['markread'] = 'Mark read';
$string['markreadbutton'] = 'Mark<br />read';
$string['markunread'] = 'Mark unread';
$string['markunreadbutton'] = 'Mark<br />unread';
$string['maxattachments'] = 'Maximum number of attachments';
$string['maxattachments_help'] = 'This setting specifies the maximum number of files that can be attached to a quora post.';
$string['maxattachmentsize'] = 'Maximum attachment size';
$string['maxattachmentsize_help'] = 'This setting specifies the largest size of file that can be attached to a quora post.';
$string['maxtimehaspassed'] = 'Sorry, but the maximum time for editing this post ({$a}) has passed!';
$string['message'] = 'Message';
$string['messageinboundattachmentdisallowed'] = 'Unable to post your reply, since it includes an attachment and the quora doesn\'t allow attachments.';
$string['messageinboundfilecountexceeded'] = 'Unable to post your reply, since it includes more than the maximum number of attachments allowed for the quora ({$a->quora->maxattachments}).';
$string['messageinboundfilesizeexceeded'] = 'Unable to post your reply, since the total attachment size ({$a->filesize}) is greater than the maximum size allowed for the quora ({$a->maxbytes}).';
$string['messageinboundquorahidden'] = 'Unable to post your reply, since the quora is currently unavailable.';
$string['messageinboundnopostquora'] = 'Unable to post your reply, since you do not have permission to post in the {$a->quora->name} quora.';
$string['messageinboundthresholdhit'] = 'Unable to post your reply.  You have exceeded the posting threshold set for this quora';
$string['messageprovider:digests'] = 'Subscribed quora digests';
$string['messageprovider:posts'] = 'Subscribed quora posts';
$string['missingsearchterms'] = 'The following search terms occur only in the HTML markup of this message:';
$string['modeflatnewestfirst'] = 'Display replies flat, with newest first';
$string['modeflatoldestfirst'] = 'Display replies flat, with oldest first';
$string['modenested'] = 'Display replies in nested form';
$string['modethreaded'] = 'Display replies in threaded form';
$string['modulename'] = 'Forum';
$string['modulename_help'] = 'The quora activity module enables participants to have asynchronous discussions i.e. discussions that take place over an extended period of time.

There are several quora types to choose from, such as a standard quora where anyone can start a new discussion at any time; a quora where each student can post exactly one discussion; or a question and answer quora where students must first post before being able to view other students\' posts. A teacher can allow files to be attached to quora posts. Attached images are displayed in the quora post.

Participants can subscribe to a quora to receive notifications of new quora posts. A teacher can set the subscription mode to optional, forced or auto, or prevent subscription completely. If required, students can be blocked from posting more than a given number of posts in a given time period; this can prevent individuals from dominating discussions.

Forum posts can be rated by teachers or students (peer evaluation). Ratings can be aggregated to form a final grade which is recorded in the gradebook.

Forums have many uses, such as

* A social space for students to get to know each other
* For course announcements (using a news quora with forced subscription)
* For discussing course content or reading materials
* For continuing online an issue raised previously in a face-to-face session
* For teacher-only discussions (using a hidden quora)
* A help centre where tutors and students can give advice
* A one-on-one support area for private student-teacher communications (using a quora with separate groups and with one student per group)
* For extension activities, for example ‘brain teasers’ for students to ponder and suggest solutions to';
$string['modulename_link'] = 'mod/quora/view';
$string['modulenameplural'] = 'Forums';
$string['more'] = 'more';
$string['movedmarker'] = '(Moved)';
$string['movethisdiscussionto'] = 'Move this discussion to ...';
$string['mustprovidediscussionorpost'] = 'You must provide either a discussion id or post id to export';
$string['myprofileownpost'] = 'My quora posts';
$string['myprofileowndis'] = 'My quora discussions';
$string['myprofileotherdis'] = 'Forum discussions';
$string['namenews'] = 'News quora';
$string['namenews_help'] = 'The news quora is a special quora for announcements that is automatically created when a course is created. A course can have only one news quora. Only teachers and administrators can post in the news quora. The "Latest news" block will display recent discussions from the news quora.';
$string['namesocial'] = 'Social quora';
$string['nameteacher'] = 'Teacher quora';
$string['nextdiscussiona'] = 'Next discussion: {$a}';
$string['newquoraposts'] = 'New quora posts';
$string['noattachments'] = 'There are no attachments to this post';
$string['nodiscussions'] = 'There are no discussion topics yet in this quora';
$string['nodiscussionsstartedby'] = '{$a} has not started any discussions';
$string['nodiscussionsstartedbyyou'] = 'You haven\'t started any discussions yet';
$string['noguestpost'] = 'Sorry, guests are not allowed to post.';
$string['noguestsubscribe'] = 'Sorry, guests are not allowed to subscribe.';
$string['noguesttracking'] = 'Sorry, guests are not allowed to set tracking options.';
$string['nomorepostscontaining'] = 'No more posts containing \'{$a}\' were found';
$string['nonews'] = 'No news has been posted yet';
$string['noonecansubscribenow'] = 'Subscriptions are now disallowed';
$string['nopermissiontosubscribe'] = 'You do not have the permission to view quora subscribers';
$string['nopermissiontoview'] = 'You do not have permissions to view this post';
$string['nopostquora'] = 'Sorry, you are not allowed to post to this quora';
$string['noposts'] = 'No posts';
$string['nopostsmadebyuser'] = '{$a} has made no posts';
$string['nopostsmadebyyou'] = 'You haven\'t made any posts';
$string['noquestions'] = 'There are no questions yet in this quora';
$string['nosubscribers'] = 'There are no subscribers yet for this quora';
$string['notsubscribed'] = 'Subscribe';
$string['notexists'] = 'Discussion no longer exists';
$string['nothingnew'] = 'Nothing new for {$a}';
$string['notingroup'] = 'Sorry, but you need to be part of a group to see this quora.';
$string['notinstalled'] = 'The quora module is not installed';
$string['notpartofdiscussion'] = 'This post is not part of a discussion!';
$string['notrackquora'] = 'Don\'t track unread posts';
$string['noviewdiscussionspermission'] = 'You do not have the permission to view discussions in this quora';
$string['nowallsubscribed'] = 'All quoras in {$a} are subscribed.';
$string['nowallunsubscribed'] = 'All quoras in {$a} are not subscribed.';
$string['nownotsubscribed'] = '{$a->name} will NOT be notified of new posts in \'{$a->quora}\'';
$string['nownottracking'] = '{$a->name} is no longer tracking \'{$a->quora}\'.';
$string['nowsubscribed'] = '{$a->name} will be notified of new posts in \'{$a->quora}\'';
$string['nowtracking'] = '{$a->name} is now tracking \'{$a->quora}\'.';
$string['numposts'] = '{$a} posts';
$string['olderdiscussions'] = 'Older discussions';
$string['oldertopics'] = 'Older topics';
$string['oldpostdays'] = 'Read after days';
$string['overviewnumpostssince'] = '{$a} posts since last login';
$string['overviewnumunread'] = '{$a} total unread';
$string['page-mod-quora-x'] = 'Any quora module page';
$string['page-mod-quora-view'] = 'Forum module main page';
$string['page-mod-quora-discuss'] = 'Forum module discussion thread page';
$string['parent'] = 'Show parent';
$string['praise'] = 'praise';
$string['praisecount'] = 'praisecount';
$string['praisecounted'] = 'praisecounted';
$string['parentofthispost'] = 'Parent of this post';
$string['posttomygroups'] = 'Post a copy to all groups';
$string['posttomygroups_help'] = 'Posts a copy of this message to all groups you have access to. Participants in groups you do not have access to will not see this post';
$string['prevdiscussiona'] = 'Previous discussion: {$a}';
$string['pluginadministration'] = 'Forum administration';
$string['pluginname'] = 'Forum';
$string['postadded'] = '<p>Your post was successfully added.</p> <p>You have {$a} to edit it if you want to make any changes.</p>';
$string['postaddedsuccess'] = 'Your post was successfully added.';
$string['postaddedtimeleft'] = 'You have {$a} to edit it if you want to make any changes.';
$string['postbymailsuccess'] = 'Congratulations, your quora post with subject "{$a->subject}" was successfully added. You can view it at {$a->discussionurl}.';
$string['postbymailsuccess_html'] = 'Congratulations, your <a href="{$a->discussionurl}">quora post</a> with subject "{$a->subject}" was successfully posted.';
$string['postbyuser'] = '{$a->post} by {$a->user}';
$string['postincontext'] = 'See this post in context';
$string['postmailinfo'] = 'This is a copy of a message posted on the {$a} website.

To reply click on this link:';
$string['postmailnow'] = '<p>This post will be mailed out immediately to all quora subscribers.</p>';
$string['postmailsubject'] = '{$a->courseshortname}: {$a->subject}';
$string['postrating1'] = 'Mostly separate knowing';
$string['postrating2'] = 'Separate and connected';
$string['postrating3'] = 'Mostly connected knowing';
$string['posts'] = 'Posts';
$string['postsmadebyuser'] = 'Posts made by {$a}';
$string['postsmadebyuserincourse'] = 'Posts made by {$a->fullname} in {$a->coursename}';
$string['posttoquora'] = 'Post to quora';
$string['postupdated'] = 'Your post was updated';
$string['potentialsubscribers'] = 'Potential subscribers';
$string['processingdigest'] = 'Processing email digest for user {$a}';
$string['processingpost'] = 'Processing post {$a}';
$string['prune'] = 'Split';
$string['prunedpost'] = 'A new discussion has been created from that post';
$string['pruneheading'] = 'Split the discussion and move this post to a new discussion';
$string['qandaquora'] = 'Q and A quora';
$string['qandanotify'] = 'This is a question and answer quora. In order to see other responses to these questions, you must first post your answer';
$string['re'] = 'Re:';
$string['readtherest'] = 'Read the rest of this topic';
$string['replies'] = 'Replies';
$string['repliesmany'] = '{$a} replies so far';
$string['repliesone'] = '{$a} reply so far';
$string['reply'] = 'Reply';
$string['replyquora'] = 'Reply to quora';
$string['replytopostbyemail'] = 'You can reply to this via email.';
$string['replytouser'] = 'Use email address in reply';
$string['reply_handler'] = 'Reply to quora posts via email';
$string['reply_handler_name'] = 'Reply to quora posts';
$string['resetquoras'] = 'Delete posts from';
$string['resetquorasall'] = 'Delete all posts';
$string['resetdigests'] = 'Delete all per-user quora digest preferences';
$string['resetsubscriptions'] = 'Delete all quora subscriptions';
$string['resettrackprefs'] = 'Delete all quora tracking preferences';
$string['rsssubscriberssdiscussions'] = 'RSS feed of discussions';
$string['rsssubscriberssposts'] = 'RSS feed of posts';
$string['rssarticles'] = 'Number of RSS recent articles';
$string['rssarticles_help'] = 'This setting specifies the number of articles (either discussions or posts) to include in the RSS feed. Between 5 and 20 generally acceptable.';
$string['rsstype'] = 'RSS feed for this activity';
$string['rsstype_help'] = 'To enable the RSS feed for this activity, select either discussions or posts to be included in the feed.';
$string['rsstypedefault'] = 'RSS feed type';
$string['search'] = 'Search';
$string['searchdatefrom'] = 'Posts must be newer than this';
$string['searchdateto'] = 'Posts must be older than this';
$string['searchquoraintro'] = 'Please enter search terms into one or more of the following fields:';
$string['searchquoras'] = 'Search quoras';
$string['searchfullwords'] = 'These words should appear as whole words';
$string['searchnotwords'] = 'These words should NOT be included';
$string['searcholderposts'] = 'Search older posts...';
$string['searchphrase'] = 'This exact phrase must appear in the post';
$string['searchresults'] = 'Search results';
$string['searchsubject'] = 'These words should be in the subject';
$string['searchuser'] = 'This name should match the author';
$string['searchuserid'] = 'The Moodle ID of the author';
$string['searchwhichquoras'] = 'Choose which quoras to search';
$string['searchwords'] = 'These words can appear anywhere in the post';
$string['seeallposts'] = 'See all posts made by this user';
$string['shortpost'] = 'Short post';
$string['showsubscribers'] = 'Show/edit current subscribers';
$string['singlequora'] = 'A single simple discussion';
$string['smallmessage'] = '{$a->user} posted in {$a->quoraname}';
$string['startedby'] = 'Started by';
$string['subject'] = 'Subject';
$string['subscribe'] = 'Subscribe to this quora';
$string['subscribediscussion'] = 'Subscribe to this discussion';
$string['subscribeall'] = 'Subscribe everyone to this quora';
$string['subscribeenrolledonly'] = 'Sorry, only enrolled users are allowed to subscribe to quora post notifications.';
$string['subscribed'] = 'Subscribed';
$string['subscribenone'] = 'Unsubscribe everyone from this quora';
$string['subscribers'] = 'Subscribers';
$string['subscribersto'] = 'Subscribers to \'{$a}\'';
$string['subscribestart'] = 'Send me notifications of new posts in this quora';
$string['subscribestop'] = 'I don\'t want to be notified of new posts in this quora';
$string['subscription'] = 'Subscription';
$string['subscription_help'] = 'If you are subscribed to a quora it means you will receive notification of new quora posts. Usually you can choose whether you wish to be subscribed, though sometimes subscription is forced so that everyone receives notifications.';
$string['subscriptionandtracking'] = 'Subscription and tracking';
$string['subscriptionmode'] = 'Subscription mode';
$string['subscriptionmode_help'] = 'When a participant is subscribed to a quora it means they will receive quora post notifications. There are 4 subscription mode options:

* Optional subscription - Participants can choose whether to be subscribed
* Forced subscription - Everyone is subscribed and cannot unsubscribe
* Auto subscription - Everyone is subscribed initially but can choose to unsubscribe at any time
* Subscription disabled - Subscriptions are not allowed

Note: Any subscription mode changes will only affect users who enrol in the course in the future, and not existing users.';
$string['subscriptionoptional'] = 'Optional subscription';
$string['subscriptionforced'] = 'Forced subscription';
$string['subscriptionauto'] = 'Auto subscription';
$string['subscriptiondisabled'] = 'Subscription disabled';
$string['subscriptions'] = 'Subscriptions';
$string['thisquoraisthrottled'] = 'This quora has a limit to the number of quora postings you can make in a given time period - this is currently set at {$a->blockafter} posting(s) in {$a->blockperiod}';
$string['timedposts'] = 'Timed posts';
$string['timestartenderror'] = 'Display end date cannot be earlier than the start date';
$string['trackquora'] = 'Track unread posts';
$string['tracking'] = 'Track';
$string['trackingoff'] = 'Off';
$string['trackingon'] = 'Forced';
$string['trackingoptional'] = 'Optional';
$string['trackingtype'] = 'Read tracking';
$string['trackingtype_help'] = 'If enabled, participants can track read and unread posts in the quora and in discussions. There are three options:

* Optional - Participants can choose whether to turn tracking on or off via a link in the administration block. Forum tracking must also be enabled in the user\'s profile settings.
* Forced - Tracking is always on, regardless of user setting. Available depending on administrative setting.
* Off - Read and unread posts are not tracked.';
$string['unread'] = 'Unread';
$string['unreadposts'] = 'Unread posts';
$string['unreadpostsnumber'] = '{$a} unread posts';
$string['unreadpostsone'] = '1 unread post';
$string['unsubscribe'] = 'Unsubscribe from this quora';
$string['unsubscribediscussion'] = 'Unsubscribe from this discussion';
$string['unsubscribeall'] = 'Unsubscribe from all quoras';
$string['unsubscribeallconfirm'] = 'You are currently subscribed to {$a->quoras} quoras, and {$a->discussions} discussions. Do you really want to unsubscribe from all quoras and discussions, and disable discussion auto-subscription?';
$string['unsubscribeallconfirmquoras'] = 'You are currently subscribed to {$a->quoras} quoras. Do you really want to unsubscribe from all quoras and disable discussion auto-subscription?';
$string['unsubscribeallconfirmdiscussions'] = 'You are currently subscribed to {$a->discussions} discussions. Do you really want to unsubscribe from all discussions and disable discussion auto-subscription?';
$string['unsubscribealldone'] = 'All optional quora subscriptions were removed. You will still receive notifications from quoras with forced subscription. To manage quora notifications go to Messaging in My Profile Settings.';
$string['unsubscribeallempty'] = 'You are not subscribed to any quoras. To disable all notifications from this server go to Messaging in My Profile Settings.';
$string['unsubscribed'] = 'Unsubscribed';
$string['unsubscribeshort'] = 'Unsubscribe';
$string['usermarksread'] = 'Manual message read marking';
$string['viewalldiscussions'] = 'View all discussions';
$string['warnafter'] = 'Post threshold for warning';
$string['warnafter_help'] = 'Students can be warned as they approach the maximum number of posts allowed in a given period. This setting specifies after how many posts they are warned. Users with the capability mod/quora:postwithoutthrottling are exempt from post limits.';
$string['warnformorepost'] = 'Warning! There is more than one discussion in this quora - using the most recent';
$string['yournewquestion'] = 'Your new question';
$string['yournewtopic'] = 'Your new discussion topic';
$string['yourreply'] = 'Your reply';
