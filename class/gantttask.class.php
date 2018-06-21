<?php

dol_include_once('/projet/class/task.class.php');

class GanttTask extends Task {

    public function getNomUrl($withpicto = 0, $option = '', $mode = 'task', $addlabel = 0, $sep = ' - ', $notooltip = 0, $save_lastsearch_value = -1) {
        global $conf, $langs, $user;

        if (! empty($conf->dol_no_mouse_hover)) $notooltip=1;   // Force disable tooltips

        $result='';
        $label = '<u>' . $langs->trans("ShowTask") . '</u>';
        if (! empty($this->ref))
            $label .= '<br><b>' . $langs->trans('Ref') . ':</b> ' . $this->ref;

            if (! empty($this->label))    $label .= '<br><b>' . $langs->trans('LabelTask') . ':</b> ' . $this->label;

                if ($this->date_start || $this->date_end)
                {
                    $label .= "<br>".get_date_range($this->date_start,$this->date_end,'',$langs,0);
                }

                $url = DOL_URL_ROOT.'/projet/tasks/task.php?id='.$this->id.'&withproject=1';

                $linkclose = '';

                    if (! empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER))
                    {
                        $label=$langs->trans("ShowTask");
                        $linkclose.=' alt="'.dol_escape_htmltag($label, 1).'"';
                    }
                    $linkclose.= ' title="'.dol_escape_htmltag($label, 1).'"';
                    $linkclose.=' class="classfortooltip cal_event"';


                $linkstart = '<a href="'.$url.'"';
                $linkstart.=$linkclose.'>';
                $linkend='</a>';

                $picto='projecttask';

                $result .= $linkstart;
                if ($withpicto) $result.=img_object(($notooltip?'':$label), $picto, ($notooltip?(($withpicto != 2) ? 'class="paddingright"' : ''):'class="'.(($withpicto != 2) ? 'paddingright ' : '').'classfortooltip"'), 0, 0, $notooltip?0:1);
                if ($withpicto != 2) $result.= $this->ref.' '.$this->label;
                $result .= $linkend;
                if ($withpicto != 2) $result.=(($addlabel && $this->label) ? $sep . dol_trunc($this->label, ($addlabel > 1 ? $addlabel : 0)) : '');

                return $result;

    }

}
