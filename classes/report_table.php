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
 * The report that displays issued certificates.
 *
 * @package    mod_customcert
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customcert;

use customcertelement_expiry\element as expiry_element;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Class for the report that displays issued certificates.
 *
 * @package    mod_customcert
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_table extends \table_sql {

    /**
     * @var int $customcertid The custom certificate id
     */
    protected $customcertid;

    /**
     * @var \stdClass $cm The course module.
     */
    protected $cm;

    /**
     * @var bool $groupmode are we in group mode?
     */
    protected $groupmode;

    /**
     * Sets up the table.
     *
     * @param int $customcertid
     * @param \stdClass $cm the course module
     * @param bool $groupmode are we in group mode?
     * @param string|null $download The file type, null if we are not downloading
     */
    public function __construct($customcertid, $cm, $groupmode, $download = null) {
        parent::__construct('mod_customcert_report_table');

        $context = \context_module::instance($cm->id);
        $extrafields = \core_user\fields::for_identity($context)->get_required_fields();
        $showexpiry = false;

        if (class_exists('\customcertelement_expiry\element')) {
            $showexpiry = expiry_element::has_expiry($customcertid);
        }

        $columns = [];
        $columns[] = 'firstname';      // Eesnimi
        $columns[] = 'lastname';       // Perekonnanimi
        $columns[] = 'email';          // Meiliaadress
        $columns[] = 'idnumber';       // Isikukood
        $columns[] = 'timecreated';    // Väljastamise kuupäev
        $columns[] = 'code';           // Tunnistuse kood
        $columns[] = 'certificateid';  // Tunnistuse number
        // Check if we were passed a filename, which means we want to download it.
        if ($download) {
            $this->is_downloading($download, 'customcert-report');
        }

        if (!$this->is_downloading()) {
            $columns[] = 'download';
            $headers[] = get_string('file');
        }

        if (!$this->is_downloading() && has_capability('mod/customcert:manage', $context)) {
            $columns[] = 'actions';
            $headers[] = '';
        }
        $this->define_columns($columns);
        
        $headers = [];
        $headers[] = get_string('firstname');        // Eesnimi
        $headers[] = get_string('lastname');         // Perekonnanimi
        $headers[] = get_string('email');            // Meiliaadress
        $headers[] = get_string('idnumber', 'customcert'); // Isikukood
        $headers[] = get_string('receiveddate', 'customcert'); // Väljastamise kuupäev
        $headers[] = get_string('code', 'customcert');      // Tunnistuse kood
        $headers[] = get_string('certificateid', 'customcert'); // Tunnistuse number



        $this->define_headers($headers);
      
        $this->collapsible(false);
        $this->sortable(true);
        $this->no_sorting('code');
        $this->no_sorting('download');
        $this->no_sorting('certificateid');
        $this->no_sorting('idnumber');
        $this->is_downloadable(true);

        $this->customcertid = $customcertid;
        $this->cm = $cm;
        $this->groupmode = $groupmode;
    }


    
    /**
     * Generate the fullname column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_fullname($user) {
        global $OUTPUT;

        if (!$this->is_downloading()) {
            return $OUTPUT->user_picture($user) . ' ' . fullname($user);
        } else {
            return fullname($user);
        }
    }
    /**
         * Generate the idnumber column.
         *
         * @param \stdClass $user
         * @return string
         */
        public function col_idnumber($user) {
            global $DB;

            // Pärime kasutaja idnumber
            $userid = $user->id; // Kasutaja ID
            $userrecord = $DB->get_record('user', ['id' => $userid], 'idnumber'); // Pärime idnumber

            return isset($userrecord->idnumber) ? $userrecord->idnumber : ''; // Tagastame idnumberi või tühi string
        }
    /**
     * Generate the certificate time created column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_timecreated($user) {
        if ($this->is_downloading() === '') {
            return userdate($user->timecreated);
        }
        $format = '%Y-%m-%d %H:%M';
        return userdate($user->timecreated, $format);
    }

    /**
     * Generate the optional certificate expires time column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_timeexpires($user) {
        if ($this->is_downloading() === '') {
            return expiry_element::get_expiry_html($this->customcertid, $user->id);
        }
        $format = '%Y-%m-%d %H:%M';
        return userdate(expiry_element::get_expiry_date($this->customcertid, $user->id), $format);
    }

    /**
     * Generate the code column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_code($user) {
        return $user->code;
    }

    /**
     * Generate the certificate ID column (with leading zeros).
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_certificateid($user) {
        global $DB;

        // Vaikimisi placeholder ID.
        $certificateid = '00000';  

        // Leia sertifikaadi ID kasutaja ja customcerti ID põhjal.
        $issue = $DB->get_record('customcert_issues', ['userid' => $user->id, 'customcertid' => $this->customcertid], '*', IGNORE_MULTIPLE);
        if ($issue && !empty($issue->id)) {
            // Lisa juhtnullid, et ID oleks kuuekohaline.
            $certificateid = str_pad($issue->id, 6, '0', STR_PAD_LEFT);  
        }

        return $certificateid;
    }


    /**
     * Generate the download column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_download($user) {
        global $OUTPUT;

        $icon = new \pix_icon('download', get_string('download'), 'customcert');
        $link = new \moodle_url('/mod/customcert/view.php',
            [
                'id' => $this->cm->id,
                'downloadissue' => $user->id,
            ]
        );

        return $OUTPUT->action_link($link, '', null, null, $icon);
    }

    /**
     * Generate the actions column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_actions($user) {
        global $OUTPUT;

        $icon = new \pix_icon('i/delete', get_string('delete'));
        $link = new \moodle_url('/mod/customcert/view.php',
            [
                'id' => $this->cm->id,
                'deleteissue' => $user->issueid,
                'sesskey' => sesskey(),
            ]
        );

        return $OUTPUT->action_icon($link, $icon, null, ['class' => 'action-icon delete-icon']);
    }

    /**
     * Query the reader.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar.
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        $total = \mod_customcert\certificate::get_number_of_issues($this->customcertid, $this->cm, $this->groupmode);

        $this->pagesize($pagesize, $total);

        $this->rawdata = \mod_customcert\certificate::get_issues($this->customcertid, $this->groupmode, $this->cm,
            $this->get_page_start(), $this->get_page_size(), $this->get_sql_sort());

        // Set initial bars.
        if ($useinitialsbar) {
            $this->initialbars($total > $pagesize);
        }
    }

    /**
     * Download the data.
     */
    public function download() {
        \core\session\manager::write_close();
        $total = \mod_customcert\certificate::get_number_of_issues($this->customcertid, $this->cm, $this->groupmode);
        $this->out($total, false);
        exit;
    }
}
