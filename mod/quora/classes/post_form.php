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
 * File containing the form definition to post in the quora.
 *
 * @package   mod_quora
 * @copyright Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Class to post in a quora.
 *
 * @package   mod_quora
 * @copyright Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quora_post_form extends moodleform {

    /**
     * Returns the options array to use in filemanager for quora attachments
     *
     * @param stdClass $quora
     * @return array
     */
    public static function attachment_options($quora) {
        global $COURSE, $PAGE, $CFG;
        $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes, $quora->maxbytes);
        return array(
            'subdirs' => 0,
            'maxbytes' => $maxbytes,
            'maxfiles' => $quora->maxattachments,
            'accepted_types' => '*',
            'return_types' => FILE_INTERNAL
        );
    }

    /**
     * Returns the options array to use in quora text editor
     *
     * @param context_module $context
     * @param int $postid post id, use null when adding new post
     * @return array
     */
    public static function editor_options(context_module $context, $postid) {
        global $COURSE, $PAGE, $CFG;
        // TODO: add max files and max size support
        $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes);
        return array(
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'maxbytes' => $maxbytes,
            'trusttext'=> true,
            'return_types'=> FILE_INTERNAL | FILE_EXTERNAL,
            'subdirs' => file_area_contains_subdirs($context, 'mod_quora', 'post', $postid)
        );
    }

    /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        global $CFG, $OUTPUT;

        $mform =& $this->_form;

        $course = $this->_customdata['course'];
        $cm = $this->_customdata['cm'];
        $coursecontext = $this->_customdata['coursecontext'];
        $modcontext = $this->_customdata['modcontext'];
        $quora = $this->_customdata['quora'];
        $post = $this->_customdata['post'];
        $subscribe = $this->_customdata['subscribe'];
        $edit = $this->_customdata['edit'];
        $thresholdwarning = $this->_customdata['thresholdwarning'];

        $mform->addElement('header', 'general', '');//fill in the data depending on page params later using set_data

        // If there is a warning message and we are not editing a post we need to handle the warning.
        if (!empty($thresholdwarning) && !$edit) {
            // Here we want to display a warning if they can still post but have reached the warning threshold.
            if ($thresholdwarning->canpost) {
                $message = get_string($thresholdwarning->errorcode, $thresholdwarning->module, $thresholdwarning->additional);
                $mform->addElement('html', $OUTPUT->notification($message));
            }
        }

        $mform->addElement('text', 'subject', get_string('subject', 'quora'), 'size="48"');
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('required'), 'required', null, 'client');
        $mform->addRule('subject', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('editor', 'message', get_string('message', 'quora'), null, self::editor_options($modcontext, (empty($post->id) ? null : $post->id)));
        $mform->setType('message', PARAM_RAW);
        $mform->addRule('message', get_string('required'), 'required', null, 'client');

        $manageactivities = has_capability('moodle/course:manageactivities', $coursecontext);

        if (\mod_quora\subscriptions::is_forcesubscribed($quora)) {
            $mform->addElement('checkbox', 'discussionsubscribe', get_string('discussionsubscription', 'quora'));
            $mform->freeze('discussionsubscribe');
            $mform->setDefaults('discussionsubscribe', 0);
            $mform->addHelpButton('discussionsubscribe', 'forcesubscribed', 'quora');

        } else if (\mod_quora\subscriptions::subscription_disabled($quora) && !$manageactivities) {
            $mform->addElement('checkbox', 'discussionsubscribe', get_string('discussionsubscription', 'quora'));
            $mform->freeze('discussionsubscribe');
            $mform->setDefaults('discussionsubscribe', 0);
            $mform->addHelpButton('discussionsubscribe', 'disallowsubscription', 'quora');

        } else {
            $mform->addElement('checkbox', 'discussionsubscribe', get_string('discussionsubscription', 'quora'));
            $mform->addHelpButton('discussionsubscribe', 'discussionsubscription', 'quora');
        }

        if (!empty($quora->maxattachments) && $quora->maxbytes != 1 && has_capability('mod/quora:createattachment', $modcontext))  {  //  1 = No attachments at all
            $mform->addElement('filemanager', 'attachments', get_string('attachment', 'quora'), null, self::attachment_options($quora));
            $mform->addHelpButton('attachments', 'attachment', 'quora');
        }

        if (empty($post->id) && $manageactivities) {
            $mform->addElement('checkbox', 'mailnow', get_string('mailnow', 'quora'));
        }

        if (!empty($CFG->quora_enabletimedposts) && !$post->parent && has_capability('mod/quora:viewhiddentimedposts', $coursecontext)) { // hack alert
            $mform->addElement('header', 'displayperiod', get_string('displayperiod', 'quora'));

            $mform->addElement('date_selector', 'timestart', get_string('displaystart', 'quora'), array('optional'=>true));
            $mform->addHelpButton('timestart', 'displaystart', 'quora');

            $mform->addElement('date_selector', 'timeend', get_string('displayend', 'quora'), array('optional'=>true));
            $mform->addHelpButton('timeend', 'displayend', 'quora');

        } else {
            $mform->addElement('hidden', 'timestart');
            $mform->setType('timestart', PARAM_INT);
            $mform->addElement('hidden', 'timeend');
            $mform->setType('timeend', PARAM_INT);
            $mform->setConstants(array('timestart'=> 0, 'timeend'=>0));
        }

        if ($groupmode = groups_get_activity_groupmode($cm, $course)) { // hack alert
            $groupdata = groups_get_activity_allowed_groups($cm);
            $groupcount = count($groupdata);
            $groupinfo = array();
            $modulecontext = context_module::instance($cm->id);

            // Check whether the user has access to all groups in this quora from the accessallgroups cap.
            if ($groupmode == VISIBLEGROUPS || has_capability('moodle/site:accessallgroups', $modulecontext)) {
                // Only allow posting to all groups if the user has access to all groups.
                $groupinfo = array('0' => get_string('allparticipants'));
                $groupcount++;
            }

            $contextcheck = has_capability('mod/quora:movediscussions', $modulecontext) && empty($post->parent) && $groupcount > 1;
            if ($contextcheck) {
                if (has_capability('mod/quora:canposttomygroups', $modulecontext)
                            && !isset($post->edit)) {
                    $mform->addElement('checkbox', 'posttomygroups', get_string('posttomygroups', 'quora'));
                    $mform->addHelpButton('posttomygroups', 'posttomygroups', 'quora');
                    $mform->disabledIf('groupinfo', 'posttomygroups', 'checked');
                }

                foreach ($groupdata as $grouptemp) {
                    $groupinfo[$grouptemp->id] = $grouptemp->name;
                }
                $mform->addElement('select','groupinfo', get_string('group'), $groupinfo);
                $mform->setDefault('groupinfo', $post->groupid);
                $mform->setType('groupinfo', PARAM_INT);
            } else {
                if (empty($post->groupid)) {
                    $groupname = get_string('allparticipants');
                } else {
                    $groupname = format_string($groupdata[$post->groupid]->name);
                }
                $mform->addElement('static', 'groupinfo', get_string('group'), $groupname);
            }
        }
        //-------------------------------------------------------------------------------
        // buttons
        if (isset($post->edit)) { // hack alert
            $submit_string = get_string('savechanges');
        } else {
            $submit_string = get_string('posttoquora', 'quora');
        }

        $this->add_action_buttons(true, $submit_string);

        $mform->addElement('hidden', 'course');
        $mform->setType('course', PARAM_INT);

        $mform->addElement('hidden', 'quora');
        $mform->setType('quora', PARAM_INT);

        $mform->addElement('hidden', 'discussion');
        $mform->setType('discussion', PARAM_INT);

        $mform->addElement('hidden', 'parent');
        $mform->setType('parent', PARAM_INT);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('hidden', 'groupid');
        $mform->setType('groupid', PARAM_INT);

        $mform->addElement('hidden', 'edit');
        $mform->setType('edit', PARAM_INT);

        $mform->addElement('hidden', 'reply');
        $mform->setType('reply', PARAM_INT);
    }

    /**
     * Form validation
     *
     * @param array $data data from the form.
     * @param array $files files uploaded.
     * @return array of errors.
     */
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (($data['timeend']!=0) && ($data['timestart']!=0) && $data['timeend'] <= $data['timestart']) {
            $errors['timeend'] = get_string('timestartenderror', 'quora');
        }
        if (empty($data['message']['text'])) {
            $errors['message'] = get_string('erroremptymessage', 'quora');
        }
        if (empty($data['subject'])) {
            $errors['subject'] = get_string('erroremptysubject', 'quora');
        }
        return $errors;
    }
}
