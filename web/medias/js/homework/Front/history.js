$(document).ready(function ()
{
    $('#homework').infinitescroll({
        navSelector  : "div.homeworknav",            
        nextSelector : "div.homeworknav a:first",    
        itemSelector : "div.homeworkdue",
        debug        : true
    });

    $('.content-school-subject a.btn').click(function (event)
    {
        event.preventDefault();
        var $this = $(this);
        var hdId = $(this).attr('data-task-id');
        var taskDone = $(this).attr('data-task-done');
        
        if(taskDone == 0)
        {
            $.ajax(
            {
                url: Routing.generate('BNSAppHomeworkBundle_frontajax_task_status', { hdId: hdId }),
                type: 'POST',
                success: function (data)
                {
                    $this.removeClass('not-validate').addClass('btn-success');
                    $this.attr('data-task-done', 1);
                    $this.html('<span class="icons-validated"></span>' + $(this).attr('data-label-validate'));
                },
                error: function (data)
                {

                }
            });
        }
    });
});