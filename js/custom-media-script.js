jQuery(document).ready(function($) {
    // Add a button to select all unused media
    $('<button/>', {
        text: 'Select All Unused',
        id: 'select-unused-media',
        class: 'button'
    }).prependTo('.tablenav.top .actions.bulkactions');

    // On button click, highlight unused media rows
    $('#select-unused-media').on('click', function() {
        $('tr').each(function() {
            var $row = $(this);
            if ($row.find('em:contains("Not used")').length > 0) {
                $row.css('background-color', 'red');
                $row.find('input[type="checkbox"]').prop('checked', true);
            }
        });
    });
}); 