<?php

return [
    /** Максимум соавторов с правом edit/manage на один курс (без владельца). */
    'course_collaborator_limit' => (int) env('PORTAL_COURSE_COLLABORATOR_LIMIT', 5),
];
