<?php
// This file is part of the customcert module for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Customcert element for Certificate ID.
 *
 * @package    customcertelement_certificateid
 * @author     Argo Ilves - argoilves@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_certificateid;

defined('MOODLE_INTERNAL') || die();

class element extends \mod_customcert\element {

    /**
     * Renders the element on the PDF.
     *
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     */
    public function render($pdf, $preview, $user) {
        global $DB;

        $certificateid = '00000';  // Default for placeholder.

        if (!$preview) {
            $certificateid = $this->get_certificateid();
            $issue = $DB->get_record('customcert_issues', ['userid' => $user->id, 'customcertid' => $certificateid], '*', IGNORE_MULTIPLE);
            if ($issue && !empty($issue->id)) {
                $certificateid = $issue->id;  
            } else {
                debugging("Sertifikaadi ID ei leitud. Kasutaja ID: {$user->id}, Customcert ID: {$certificateid}");
            }
        }
        //Add leading zeros to ID to match placheolder
        $certificateid = str_pad($certificateid, 6, '0', STR_PAD_LEFT); 
        \mod_customcert\element_helper::render_content($pdf, $this, $certificateid);
    }

    /**
     * Renders the element in HTML.
     *
     * @return string HTML 
     */
    public function render_html($preview = false, $user = null) {
        global $DB;
        $certificateid = '000000';  // Default for placeholder.
        if (!$preview && $user !== null) {
            $certificateid = $this->get_certificateid();
            $issue = $DB->get_record('customcert_issues', ['userid' => $user->id, 'customcertid' => $certificateid], '*', IGNORE_MULTIPLE);
            if ($issue && !empty($issue->id)) {
                $certificateid = $issue->id;  
            } else {
                debugging("Sertifikaadi ID ei leitud. Kasutaja ID: {$user->id}, Customcert ID: {$certificateid}");
            }
        }
        //Add leading zeros to ID to match placheolder
        $certificateid = str_pad($certificateid, 6, '0', STR_PAD_LEFT);
        return \mod_customcert\element_helper::render_html_content($this, $certificateid);
    }

    /**
     * Function to get the customcert ID.
     * @return int ccertificateid
     */
    protected function get_certificateid() {
        global $DB;
        $page = $DB->get_record('customcert_pages', ['id' => $this->get_pageid()], '*', MUST_EXIST);
        return $DB->get_field('customcert', 'id', ['templateid' => $page->templateid], MUST_EXIST);
    }

    /**
     * Determines if this element can be added to a certificate.
     *
     * @return bool
     */
    public static function can_add() {
        return true;
    }
}
