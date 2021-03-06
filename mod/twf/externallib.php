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
 * External twf API
 *
 * @package    mod_twf
 * @copyright  2012 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

class mod_twf_external extends external_api {

    /**
     * Describes the parameters for get_twf.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_twfs_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(new external_value(PARAM_INT, 'course ID',
                        '', VALUE_REQUIRED, '', NULL_NOT_ALLOWED), 'Array of Course IDs', VALUE_DEFAULT, array()),
            )
        );
    }

    /**
     * Returns a list of twfs in a provided list of courses,
     * if no list is provided all twfs that the user can view
     * will be returned.
     *
     * @param array $courseids the course ids
     * @return array the twf details
     * @since Moodle 2.5
     */
    public static function get_twfs_by_courses($courseids = array()) {
        global $CFG;

        require_once($CFG->dirroot . "/mod/twf/lib.php");

        $params = self::validate_parameters(self::get_twfs_by_courses_parameters(), array('courseids' => $courseids));

        if (empty($params['courseids'])) {
            // Get all the courses the user can view.
            $courseids = array_keys(enrol_get_my_courses());
        } else {
            $courseids = $params['courseids'];
        }

        // Array to store the twfs to return.
        $arrtwfs = array();

        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Array of the courses we are going to retrieve the twfs from.
            $dbcourses = array();
            // Mod info for courses.
            $modinfocourses = array();

            // Go through the courseids and return the twfs.
            foreach ($courseids as $courseid) {
                // Check the user can function in this context.
                try {
                    $context = context_course::instance($courseid);
                    self::validate_context($context);
                    // Get the modinfo for the course.
                    $modinfocourses[$courseid] = get_fast_modinfo($courseid);
                    $dbcourses[$courseid] = $modinfocourses[$courseid]->get_course();

                } catch (Exception $e) {
                    continue;
                }
            }

            // Get the twfs in this course. This function checks users visibility permissions.
            if ($twfs = get_all_instances_in_courses("twf", $dbcourses)) {
                foreach ($twfs as $twf) {

                    $course = $dbcourses[$twf->course];
                    $cm = $modinfocourses[$course->id]->get_cm($twf->coursemodule);
                    $context = context_module::instance($cm->id);

                    // Skip twfs we are not allowed to see discussions.
                    if (!has_capability('mod/twf:viewdiscussion', $context)) {
                        continue;
                    }

                    // Format the intro before being returning using the format setting.
                    list($twf->intro, $twf->introformat) = external_format_text($twf->intro, $twf->introformat,
                                                                                    $context->id, 'mod_twf', 'intro', 0);
                    // Discussions count. This function does static request cache.
                    $twf->numdiscussions = twf_count_discussions($twf, $cm, $course);
                    $twf->cmid = $twf->coursemodule;

                    // Add the twf to the array to return.
                    $arrtwfs[$twf->id] = $twf;
                }
            }
        }

        return $arrtwfs;
    }

    /**
     * Describes the get_twf return value.
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
     public static function get_twfs_by_courses_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Forum id'),
                    'course' => new external_value(PARAM_TEXT, 'Course id'),
                    'type' => new external_value(PARAM_TEXT, 'The twf type'),
                    'name' => new external_value(PARAM_TEXT, 'Forum name'),
                    'intro' => new external_value(PARAM_RAW, 'The twf intro'),
                    'introformat' => new external_format_value('intro'),
                    'assessed' => new external_value(PARAM_INT, 'Aggregate type'),
                    'assesstimestart' => new external_value(PARAM_INT, 'Assess start time'),
                    'assesstimefinish' => new external_value(PARAM_INT, 'Assess finish time'),
                    'scale' => new external_value(PARAM_INT, 'Scale'),
                    'maxbytes' => new external_value(PARAM_INT, 'Maximum attachment size'),
                    'maxattachments' => new external_value(PARAM_INT, 'Maximum number of attachments'),
                    'forcesubscribe' => new external_value(PARAM_INT, 'Force users to subscribe'),
                    'trackingtype' => new external_value(PARAM_INT, 'Subscription mode'),
                    'rsstype' => new external_value(PARAM_INT, 'RSS feed for this activity'),
                    'rssarticles' => new external_value(PARAM_INT, 'Number of RSS recent articles'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'warnafter' => new external_value(PARAM_INT, 'Post threshold for warning'),
                    'blockafter' => new external_value(PARAM_INT, 'Post threshold for blocking'),
                    'blockperiod' => new external_value(PARAM_INT, 'Time period for blocking'),
                    'completiondiscussions' => new external_value(PARAM_INT, 'Student must create discussions'),
                    'completionreplies' => new external_value(PARAM_INT, 'Student must post replies'),
                    'completionposts' => new external_value(PARAM_INT, 'Student must post discussions or replies'),
                    'cmid' => new external_value(PARAM_INT, 'Course module id'),
                    'numdiscussions' => new external_value(PARAM_INT, 'Number of discussions in the twf', VALUE_OPTIONAL)
                ), 'twf'
            )
        );
    }

    /**
     * Describes the parameters for get_twf_discussions.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.5
     * @deprecated Moodle 2.8 MDL-46458 - Please do not call this function any more.
     * @see get_twf_discussions_paginated
     */
    public static function get_twf_discussions_parameters() {
        return new external_function_parameters (
            array(
                'twfids' => new external_multiple_structure(new external_value(PARAM_INT, 'twf ID',
                        '', VALUE_REQUIRED, '', NULL_NOT_ALLOWED), 'Array of Forum IDs', VALUE_REQUIRED),
                'limitfrom' => new external_value(PARAM_INT, 'limit from', VALUE_DEFAULT, 0),
                'limitnum' => new external_value(PARAM_INT, 'limit number', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Returns a list of twf discussions as well as a summary of the discussion
     * in a provided list of twfs.
     *
     * @param array $twfids the twf ids
     * @param int $limitfrom limit from SQL data
     * @param int $limitnum limit number SQL data
     *
     * @return array the twf discussion details
     * @since Moodle 2.5
     * @deprecated Moodle 2.8 MDL-46458 - Please do not call this function any more.
     * @see get_twf_discussions_paginated
     */
    public static function get_twf_discussions($twfids, $limitfrom = 0, $limitnum = 0) {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . "/mod/twf/lib.php");

        // Validate the parameter.
        $params = self::validate_parameters(self::get_twf_discussions_parameters(),
            array(
                'twfids'  => $twfids,
                'limitfrom' => $limitfrom,
                'limitnum'  => $limitnum,
            ));
        $twfids  = $params['twfids'];
        $limitfrom = $params['limitfrom'];
        $limitnum  = $params['limitnum'];

        // Array to store the twf discussions to return.
        $arrdiscussions = array();
        // Keep track of the users we have looked up in the DB.
        $arrusers = array();

        // Loop through them.
        foreach ($twfids as $id) {
            // Get the twf object.
            $twf = $DB->get_record('twf', array('id' => $id), '*', MUST_EXIST);
            $course = get_course($twf->course);

            $modinfo = get_fast_modinfo($course);
            $twfs  = $modinfo->get_instances_of('twf');
            $cm = $twfs[$twf->id];

            // Get the module context.
            $modcontext = context_module::instance($cm->id);

            // Validate the context.
            self::validate_context($modcontext);

            require_capability('mod/twf:viewdiscussion', $modcontext);

            // Get the discussions for this twf.
            $params = array();

            $groupselect = "";
            $groupmode = groups_get_activity_groupmode($cm, $course);

            if ($groupmode and $groupmode != VISIBLEGROUPS and !has_capability('moodle/site:accessallgroups', $modcontext)) {
                // Get all the discussions from all the groups this user belongs to.
                $usergroups = groups_get_user_groups($course->id);
                if (!empty($usergroups['0'])) {
                    list($sql, $params) = $DB->get_in_or_equal($usergroups['0']);
                    $groupselect = "AND (groupid $sql OR groupid = -1)";
                }
            }
            array_unshift($params, $id);
            $select = "twf = ? $groupselect";

            if ($discussions = $DB->get_records_select('twf_discussions', $select, $params, 'timemodified DESC', '*',
                                                            $limitfrom, $limitnum)) {

                // Check if they can view full names.
                $canviewfullname = has_capability('moodle/site:viewfullnames', $modcontext);
                // Get the unreads array, this takes a twf id and returns data for all discussions.
                $unreads = array();
                if ($cantrack = twf_tp_can_track_twfs($twf)) {
                    if ($twftracked = twf_tp_is_tracked($twf)) {
                        $unreads = twf_get_discussions_unread($cm);
                    }
                }
                // The twf function returns the replies for all the discussions in a given twf.
                $replies = twf_count_discussion_replies($id);

                foreach ($discussions as $discussion) {
                    // This function checks capabilities, timed discussions, groups and qanda twfs posting.
                    if (!twf_user_can_see_discussion($twf, $discussion, $modcontext)) {
                        continue;
                    }

                    $usernamefields = user_picture::fields();
                    // If we don't have the users details then perform DB call.
                    if (empty($arrusers[$discussion->userid])) {
                        $arrusers[$discussion->userid] = $DB->get_record('user', array('id' => $discussion->userid),
                                $usernamefields, MUST_EXIST);
                    }
                    // Get the subject.
                    $subject = $DB->get_field('twf_posts', 'subject', array('id' => $discussion->firstpost), MUST_EXIST);
                    // Create object to return.
                    $return = new stdClass();
                    $return->id = (int) $discussion->id;
                    $return->course = $discussion->course;
                    $return->twf = $discussion->twf;
                    $return->name = $discussion->name;
                    $return->userid = $discussion->userid;
                    $return->groupid = $discussion->groupid;
                    $return->assessed = $discussion->assessed;
                    $return->timemodified = (int) $discussion->timemodified;
                    $return->usermodified = $discussion->usermodified;
                    $return->timestart = $discussion->timestart;
                    $return->timeend = $discussion->timeend;
                    $return->firstpost = (int) $discussion->firstpost;
                    $return->firstuserfullname = fullname($arrusers[$discussion->userid], $canviewfullname);
                    $return->firstuserimagealt = $arrusers[$discussion->userid]->imagealt;
                    $return->firstuserpicture = $arrusers[$discussion->userid]->picture;
                    $return->firstuseremail = $arrusers[$discussion->userid]->email;
                    $return->subject = $subject;
                    $return->numunread = '';
                    if ($cantrack && $twftracked) {
                        if (isset($unreads[$discussion->id])) {
                            $return->numunread = (int) $unreads[$discussion->id];
                        }
                    }
                    // Check if there are any replies to this discussion.
                    if (!empty($replies[$discussion->id])) {
                         $return->numreplies = (int) $replies[$discussion->id]->replies;
                         $return->lastpost = (int) $replies[$discussion->id]->lastpostid;
                    } else { // No replies, so the last post will be the first post.
                        $return->numreplies = 0;
                        $return->lastpost = (int) $discussion->firstpost;
                    }
                    // Get the last post as well as the user who made it.
                    $lastpost = $DB->get_record('twf_posts', array('id' => $return->lastpost), '*', MUST_EXIST);
                    if (empty($arrusers[$lastpost->userid])) {
                        $arrusers[$lastpost->userid] = $DB->get_record('user', array('id' => $lastpost->userid),
                                $usernamefields, MUST_EXIST);
                    }
                    $return->lastuserid = $lastpost->userid;
                    $return->lastuserfullname = fullname($arrusers[$lastpost->userid], $canviewfullname);
                    $return->lastuserimagealt = $arrusers[$lastpost->userid]->imagealt;
                    $return->lastuserpicture = $arrusers[$lastpost->userid]->picture;
                    $return->lastuseremail = $arrusers[$lastpost->userid]->email;
                    // Add the discussion statistics to the array to return.
                    $arrdiscussions[$return->id] = (array) $return;
                }
            }
        }

        return $arrdiscussions;
    }

    /**
     * Describes the get_twf_discussions return value.
     *
     * @return external_single_structure
     * @since Moodle 2.5
     * @deprecated Moodle 2.8 MDL-46458 - Please do not call this function any more.
     * @see get_twf_discussions_paginated
     */
     public static function get_twf_discussions_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Forum id'),
                    'course' => new external_value(PARAM_INT, 'Course id'),
                    'twf' => new external_value(PARAM_INT, 'The twf id'),
                    'name' => new external_value(PARAM_TEXT, 'Discussion name'),
                    'userid' => new external_value(PARAM_INT, 'User id'),
                    'groupid' => new external_value(PARAM_INT, 'Group id'),
                    'assessed' => new external_value(PARAM_INT, 'Is this assessed?'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'usermodified' => new external_value(PARAM_INT, 'The id of the user who last modified'),
                    'timestart' => new external_value(PARAM_INT, 'Time discussion can start'),
                    'timeend' => new external_value(PARAM_INT, 'Time discussion ends'),
                    'firstpost' => new external_value(PARAM_INT, 'The first post in the discussion'),
                    'firstuserfullname' => new external_value(PARAM_TEXT, 'The discussion creators fullname'),
                    'firstuserimagealt' => new external_value(PARAM_TEXT, 'The discussion creators image alt'),
                    'firstuserpicture' => new external_value(PARAM_INT, 'The discussion creators profile picture'),
                    'firstuseremail' => new external_value(PARAM_TEXT, 'The discussion creators email'),
                    'subject' => new external_value(PARAM_TEXT, 'The discussion subject'),
                    'numreplies' => new external_value(PARAM_TEXT, 'The number of replies in the discussion'),
                    'numunread' => new external_value(PARAM_TEXT, 'The number of unread posts, blank if this value is
                        not available due to twf settings.'),
                    'lastpost' => new external_value(PARAM_INT, 'The id of the last post in the discussion'),
                    'lastuserid' => new external_value(PARAM_INT, 'The id of the user who made the last post'),
                    'lastuserfullname' => new external_value(PARAM_TEXT, 'The last person to posts fullname'),
                    'lastuserimagealt' => new external_value(PARAM_TEXT, 'The last person to posts image alt'),
                    'lastuserpicture' => new external_value(PARAM_INT, 'The last person to posts profile picture'),
                    'lastuseremail' => new external_value(PARAM_TEXT, 'The last person to posts email'),
                ), 'discussion'
            )
        );
    }

    /**
     * Describes the parameters for get_twf_discussion_posts.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.7
     */
    public static function get_twf_discussion_posts_parameters() {
        return new external_function_parameters (
            array(
                'discussionid' => new external_value(PARAM_INT, 'discussion ID', VALUE_REQUIRED),
                'sortby' => new external_value(PARAM_ALPHA,
                    'sort by this element: id, created or modified', VALUE_DEFAULT, 'created'),
                'sortdirection' => new external_value(PARAM_ALPHA, 'sort direction: ASC or DESC', VALUE_DEFAULT, 'DESC')
            )
        );
    }

    /**
     * Returns a list of twf posts for a discussion
     *
     * @param int $discussionid the post ids
     * @param string $sortby sort by this element (id, created or modified)
     * @param string $sortdirection sort direction: ASC or DESC
     *
     * @return array the twf post details
     * @since Moodle 2.7
     */
    public static function get_twf_discussion_posts($discussionid, $sortby = "created", $sortdirection = "DESC") {
        global $CFG, $DB, $USER;

        $posts = array();
        $warnings = array();

        // Validate the parameter.
        $params = self::validate_parameters(self::get_twf_discussion_posts_parameters(),
            array(
                'discussionid' => $discussionid,
                'sortby' => $sortby,
                'sortdirection' => $sortdirection));

        // Compact/extract functions are not recommended.
        $discussionid   = $params['discussionid'];
        $sortby         = $params['sortby'];
        $sortdirection  = $params['sortdirection'];

        $sortallowedvalues = array('id', 'created', 'modified');
        if (!in_array($sortby, $sortallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortby parameter (value: ' . $sortby . '),' .
                'allowed values are: ' . implode(',', $sortallowedvalues));
        }

        $sortdirection = strtoupper($sortdirection);
        $directionallowedvalues = array('ASC', 'DESC');
        if (!in_array($sortdirection, $directionallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $sortdirection . '),' .
                'allowed values are: ' . implode(',', $directionallowedvalues));
        }

        $discussion = $DB->get_record('twf_discussions', array('id' => $discussionid), '*', MUST_EXIST);
        $twf = $DB->get_record('twf', array('id' => $discussion->twf), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $twf->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('twf', $twf->id, $course->id, false, MUST_EXIST);

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        // This require must be here, see mod/twf/discuss.php.
        require_once($CFG->dirroot . "/mod/twf/lib.php");

        // Check they have the view twf capability.
        require_capability('mod/twf:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'twf');

        if (! $post = twf_get_post_full($discussion->firstpost)) {
            throw new moodle_exception('notexists', 'twf');
        }

        // This function check groups, qanda, timed discussions, etc.
        if (!twf_user_can_see_post($twf, $discussion, $post, null, $cm)) {
            throw new moodle_exception('noviewdiscussionspermission', 'twf');
        }

        $canviewfullname = has_capability('moodle/site:viewfullnames', $modcontext);

        // We will add this field in the response.
        $canreply = twf_user_can_post($twf, $discussion, $USER, $cm, $course, $modcontext);

        $twftracked = twf_tp_is_tracked($twf);

        $sort = 'p.' . $sortby . ' ' . $sortdirection;
        $allposts = twf_get_all_discussion_posts($discussion->id, $sort, $twftracked);

        foreach ($allposts as $post) {

            if (!twf_user_can_see_post($twf, $discussion, $post, null, $cm)) {
                $warning = array();
                $warning['item'] = 'post';
                $warning['itemid'] = $post->id;
                $warning['warningcode'] = '1';
                $warning['message'] = 'You can\'t see this post';
                $warnings[] = $warning;
                continue;
            }

            // Function twf_get_all_discussion_posts adds postread field.
            // Note that the value returned can be a boolean or an integer. The WS expects a boolean.
            if (empty($post->postread)) {
                $post->postread = false;
            } else {
                $post->postread = true;
            }

            $post->canreply = $canreply;
            if (!empty($post->children)) {
                $post->children = array_keys($post->children);
            } else {
                $post->children = array();
            }

            $user = new stdclass();
            $user->id = $post->userid;
            $user = username_load_fields_from_object($user, $post);
            $post->userfullname = fullname($user, $canviewfullname);

            // We can have post written by users that are deleted. In this case, those users don't have a valid context.
            $usercontext = context_user::instance($user->id, IGNORE_MISSING);
            if ($usercontext) {
                $post->userpictureurl = moodle_url::make_webservice_pluginfile_url(
                        $usercontext->id, 'user', 'icon', null, '/', 'f1')->out(false);
            } else {
                $post->userpictureurl = '';
            }

            // Rewrite embedded images URLs.
            list($post->message, $post->messageformat) =
                external_format_text($post->message, $post->messageformat, $modcontext->id, 'mod_twf', 'post', $post->id);

            // List attachments.
            if (!empty($post->attachment)) {
                $post->attachments = array();

                $fs = get_file_storage();
                if ($files = $fs->get_area_files($modcontext->id, 'mod_twf', 'attachment', $post->id, "filename", false)) {
                    foreach ($files as $file) {
                        $filename = $file->get_filename();
                        $fileurl = moodle_url::make_webservice_pluginfile_url(
                                        $modcontext->id, 'mod_twf', 'attachment', $post->id, '/', $filename);

                        $post->attachments[] = array(
                            'filename' => $filename,
                            'mimetype' => $file->get_mimetype(),
                            'fileurl'  => $fileurl->out(false)
                        );
                    }
                }
            }

            $posts[] = $post;
        }

        $result = array();
        $result['posts'] = $posts;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_twf_discussion_posts return value.
     *
     * @return external_single_structure
     * @since Moodle 2.7
     */
    public static function get_twf_discussion_posts_returns() {
        return new external_single_structure(
            array(
                'posts' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'Post id'),
                                'discussion' => new external_value(PARAM_INT, 'Discussion id'),
                                'parent' => new external_value(PARAM_INT, 'Parent id'),
                                'userid' => new external_value(PARAM_INT, 'User id'),
                                'created' => new external_value(PARAM_INT, 'Creation time'),
                                'modified' => new external_value(PARAM_INT, 'Time modified'),
                                'mailed' => new external_value(PARAM_INT, 'Mailed?'),
                                'subject' => new external_value(PARAM_TEXT, 'The post subject'),
                                'message' => new external_value(PARAM_RAW, 'The post message'),
                                'messageformat' => new external_format_value('message'),
                                'messagetrust' => new external_value(PARAM_INT, 'Can we trust?'),
                                'attachment' => new external_value(PARAM_RAW, 'Has attachments?'),
                                'attachments' => new external_multiple_structure(
                                    new external_single_structure(
                                        array (
                                            'filename' => new external_value(PARAM_FILE, 'file name'),
                                            'mimetype' => new external_value(PARAM_RAW, 'mime type'),
                                            'fileurl'  => new external_value(PARAM_URL, 'file download url')
                                        )
                                    ), 'attachments', VALUE_OPTIONAL
                                ),
                                'totalscore' => new external_value(PARAM_INT, 'The post message total score'),
                                'mailnow' => new external_value(PARAM_INT, 'Mail now?'),
                                'children' => new external_multiple_structure(new external_value(PARAM_INT, 'children post id')),
                                'canreply' => new external_value(PARAM_BOOL, 'The user can reply to posts?'),
                                'postread' => new external_value(PARAM_BOOL, 'The post was read'),
                                'userfullname' => new external_value(PARAM_TEXT, 'Post author full name'),
                                'userpictureurl' => new external_value(PARAM_URL, 'Post author picture.', VALUE_OPTIONAL)
                            ), 'post'
                        )
                    ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_twf_discussions_paginated.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.8
     */
    public static function get_twf_discussions_paginated_parameters() {
        return new external_function_parameters (
            array(
                'twfid' => new external_value(PARAM_INT, 'twf instance id', VALUE_REQUIRED),
                'sortby' => new external_value(PARAM_ALPHA,
                    'sort by this element: id, timemodified, timestart or timeend', VALUE_DEFAULT, 'timemodified'),
                'sortdirection' => new external_value(PARAM_ALPHA, 'sort direction: ASC or DESC', VALUE_DEFAULT, 'DESC'),
                'page' => new external_value(PARAM_INT, 'current page', VALUE_DEFAULT, -1),
                'perpage' => new external_value(PARAM_INT, 'items per page', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Returns a list of twf discussions optionally sorted and paginated.
     *
     * @param int $twfid the twf instance id
     * @param string $sortby sort by this element (id, timemodified, timestart or timeend)
     * @param string $sortdirection sort direction: ASC or DESC
     * @param int $page page number
     * @param int $perpage items per page
     *
     * @return array the twf discussion details including warnings
     * @since Moodle 2.8
     */
    public static function get_twf_discussions_paginated($twfid, $sortby = 'timemodified', $sortdirection = 'DESC',
                                                    $page = -1, $perpage = 0) {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . "/mod/twf/lib.php");

        $warnings = array();
        $discussions = array();

        $params = self::validate_parameters(self::get_twf_discussions_paginated_parameters(),
            array(
                'twfid' => $twfid,
                'sortby' => $sortby,
                'sortdirection' => $sortdirection,
                'page' => $page,
                'perpage' => $perpage
            )
        );

        // Compact/extract functions are not recommended.
        $twfid        = $params['twfid'];
        $sortby         = $params['sortby'];
        $sortdirection  = $params['sortdirection'];
        $page           = $params['page'];
        $perpage        = $params['perpage'];

        $sortallowedvalues = array('id', 'timemodified', 'timestart', 'timeend');
        if (!in_array($sortby, $sortallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortby parameter (value: ' . $sortby . '),' .
                'allowed values are: ' . implode(',', $sortallowedvalues));
        }

        $sortdirection = strtoupper($sortdirection);
        $directionallowedvalues = array('ASC', 'DESC');
        if (!in_array($sortdirection, $directionallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $sortdirection . '),' .
                'allowed values are: ' . implode(',', $directionallowedvalues));
        }

        $twf = $DB->get_record('twf', array('id' => $twfid), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $twf->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('twf', $twf->id, $course->id, false, MUST_EXIST);

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        // Check they have the view twf capability.
        require_capability('mod/twf:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'twf');

        $sort = 'd.' . $sortby . ' ' . $sortdirection;
        $alldiscussions = twf_get_discussions($cm, $sort, true, -1, -1, true, $page, $perpage);

        if ($alldiscussions) {
            $canviewfullname = has_capability('moodle/site:viewfullnames', $modcontext);

            // Get the unreads array, this takes a twf id and returns data for all discussions.
            $unreads = array();
            if ($cantrack = twf_tp_can_track_twfs($twf)) {
                if ($twftracked = twf_tp_is_tracked($twf)) {
                    $unreads = twf_get_discussions_unread($cm);
                }
            }
            // The twf function returns the replies for all the discussions in a given twf.
            $replies = twf_count_discussion_replies($twfid, $sort, -1, $page, $perpage);

            foreach ($alldiscussions as $discussion) {

                // This function checks for qanda twfs.
                // Note that the twf_get_discussions returns as id the post id, not the discussion id so we need to do this.
                $discussionrec = clone $discussion;
                $discussionrec->id = $discussion->discussion;
                if (!twf_user_can_see_discussion($twf, $discussionrec, $modcontext)) {
                    $warning = array();
                    // Function twf_get_discussions returns twf_posts ids not twf_discussions ones.
                    $warning['item'] = 'post';
                    $warning['itemid'] = $discussion->id;
                    $warning['warningcode'] = '1';
                    $warning['message'] = 'You can\'t see this discussion';
                    $warnings[] = $warning;
                    continue;
                }

                $discussion->numunread = 0;
                if ($cantrack && $twftracked) {
                    if (isset($unreads[$discussion->discussion])) {
                        $discussion->numunread = (int) $unreads[$discussion->discussion];
                    }
                }

                $discussion->numreplies = 0;
                if (!empty($replies[$discussion->discussion])) {
                    $discussion->numreplies = (int) $replies[$discussion->discussion]->replies;
                }

                // Load user objects from the results of the query.
                $user = new stdclass();
                $user->id = $discussion->userid;
                $user = username_load_fields_from_object($user, $discussion);
                $discussion->userfullname = fullname($user, $canviewfullname);

                // We can have post written by users that are deleted. In this case, those users don't have a valid context.
                $usercontext = context_user::instance($user->id, IGNORE_MISSING);
                if ($usercontext) {
                    $discussion->userpictureurl = moodle_url::make_webservice_pluginfile_url(
                        $usercontext->id, 'user', 'icon', null, '/', 'f1')->out(false);
                } else {
                    $discussion->userpictureurl = '';
                }

                $usermodified = new stdclass();
                $usermodified->id = $discussion->usermodified;
                $usermodified = username_load_fields_from_object($usermodified, $discussion, 'um');
                $discussion->usermodifiedfullname = fullname($usermodified, $canviewfullname);

                // We can have post written by users that are deleted. In this case, those users don't have a valid context.
                $usercontext = context_user::instance($usermodified->id, IGNORE_MISSING);
                if ($usercontext) {
                    $discussion->usermodifiedpictureurl = moodle_url::make_webservice_pluginfile_url(
                        $usercontext->id, 'user', 'icon', null, '/', 'f1')->out(false);
                } else {
                    $discussion->usermodifiedpictureurl = '';
                }

                // Rewrite embedded images URLs.
                list($discussion->message, $discussion->messageformat) =
                    external_format_text($discussion->message, $discussion->messageformat,
                                            $modcontext->id, 'mod_twf', 'post', $discussion->id);

                // List attachments.
                if (!empty($discussion->attachment)) {
                    $discussion->attachments = array();

                    $fs = get_file_storage();
                    if ($files = $fs->get_area_files($modcontext->id, 'mod_twf', 'attachment',
                                                        $discussion->id, "filename", false)) {
                        foreach ($files as $file) {
                            $filename = $file->get_filename();

                            $discussion->attachments[] = array(
                                'filename' => $filename,
                                'mimetype' => $file->get_mimetype(),
                                'fileurl'  => file_encode_url($CFG->wwwroot.'/webservice/pluginfile.php',
                                                '/'.$modcontext->id.'/mod_twf/attachment/'.$discussion->id.'/'.$filename)
                            );
                        }
                    }
                }

                $discussions[] = $discussion;
            }
        }

        $result = array();
        $result['discussions'] = $discussions;
        $result['warnings'] = $warnings;
        return $result;

    }

    /**
     * Describes the get_twf_discussions_paginated return value.
     *
     * @return external_single_structure
     * @since Moodle 2.8
     */
    public static function get_twf_discussions_paginated_returns() {
        return new external_single_structure(
            array(
                'discussions' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'Post id'),
                                'name' => new external_value(PARAM_TEXT, 'Discussion name'),
                                'groupid' => new external_value(PARAM_INT, 'Group id'),
                                'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                                'usermodified' => new external_value(PARAM_INT, 'The id of the user who last modified'),
                                'timestart' => new external_value(PARAM_INT, 'Time discussion can start'),
                                'timeend' => new external_value(PARAM_INT, 'Time discussion ends'),
                                'discussion' => new external_value(PARAM_INT, 'Discussion id'),
                                'parent' => new external_value(PARAM_INT, 'Parent id'),
                                'userid' => new external_value(PARAM_INT, 'User who started the discussion id'),
                                'created' => new external_value(PARAM_INT, 'Creation time'),
                                'modified' => new external_value(PARAM_INT, 'Time modified'),
                                'mailed' => new external_value(PARAM_INT, 'Mailed?'),
                                'subject' => new external_value(PARAM_TEXT, 'The post subject'),
                                'message' => new external_value(PARAM_RAW, 'The post message'),
                                'messageformat' => new external_format_value('message'),
                                'messagetrust' => new external_value(PARAM_INT, 'Can we trust?'),
                                'attachment' => new external_value(PARAM_RAW, 'Has attachments?'),
                                'attachments' => new external_multiple_structure(
                                    new external_single_structure(
                                        array (
                                            'filename' => new external_value(PARAM_FILE, 'file name'),
                                            'mimetype' => new external_value(PARAM_RAW, 'mime type'),
                                            'fileurl'  => new external_value(PARAM_URL, 'file download url')
                                        )
                                    ), 'attachments', VALUE_OPTIONAL
                                ),
                                'totalscore' => new external_value(PARAM_INT, 'The post message total score'),
                                'mailnow' => new external_value(PARAM_INT, 'Mail now?'),
                                'userfullname' => new external_value(PARAM_TEXT, 'Post author full name'),
                                'usermodifiedfullname' => new external_value(PARAM_TEXT, 'Post modifier full name'),
                                'userpictureurl' => new external_value(PARAM_URL, 'Post author picture.'),
                                'usermodifiedpictureurl' => new external_value(PARAM_URL, 'Post modifier picture.'),
                                'numreplies' => new external_value(PARAM_TEXT, 'The number of replies in the discussion'),
                                'numunread' => new external_value(PARAM_INT, 'The number of unread discussions.')
                            ), 'post'
                        )
                    ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function view_twf_parameters() {
        return new external_function_parameters(
            array(
                'twfid' => new external_value(PARAM_INT, 'twf instance id')
            )
        );
    }

    /**
     * Simulate the twf/view.php web interface page: trigger events, completion, etc...
     *
     * @param int $twfid the twf instance id
     * @return array of warnings and status result
     * @since Moodle 2.9
     * @throws moodle_exception
     */
    public static function view_twf($twfid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/twf/lib.php");

        $params = self::validate_parameters(self::view_twf_parameters(),
                                            array(
                                                'twfid' => $twfid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $twf = $DB->get_record('twf', array('id' => $params['twfid']), 'id', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($twf, 'twf');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/twf:viewdiscussion', $context, null, true, 'noviewdiscussionspermission', 'twf');

        // Call the twf/lib API.
        twf_view($twf, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function view_twf_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function view_twf_discussion_parameters() {
        return new external_function_parameters(
            array(
                'discussionid' => new external_value(PARAM_INT, 'discussion id')
            )
        );
    }

    /**
     * Simulate the twf/discuss.php web interface page: trigger events
     *
     * @param int $discussionid the discussion id
     * @return array of warnings and status result
     * @since Moodle 2.9
     * @throws moodle_exception
     */
    public static function view_twf_discussion($discussionid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/twf/lib.php");

        $params = self::validate_parameters(self::view_twf_discussion_parameters(),
                                            array(
                                                'discussionid' => $discussionid
                                            ));
        $warnings = array();

        $discussion = $DB->get_record('twf_discussions', array('id' => $params['discussionid']), '*', MUST_EXIST);
        $twf = $DB->get_record('twf', array('id' => $discussion->twf), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($twf, 'twf');

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        require_capability('mod/twf:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'twf');

        // Call the twf/lib API.
        twf_discussion_view($modcontext, $twf, $discussion);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function view_twf_discussion_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

}
