jQuery(function($){
  function renumberRows(){
    $('#bwsg-rows .bwsg-row').each(function(i){
      $(this).find('input[name^="bwsg_companies"]').each(function(){
        var name = $(this).attr('name');
        name = name.replace(/\[\d+\]/, '['+i+']');
        $(this).attr('name', name);
      });
    });
  }

  // Add new row
  $('#bwsg-add').on('click', function(e){
    e.preventDefault();
    var tpl = $('#bwsg-row-template').html();
    var i = $('#bwsg-rows .bwsg-row').length;
    tpl = tpl.replace(/__i__/g, i);
    $('#bwsg-rows').append(tpl);
  });

  // Remove row
  $('#bwsg-rows').on('click', '.bwsg-delete', function(e){
    e.preventDefault();
    $(this).closest('tr').remove();
    renumberRows();
  });

  // Choose image
  $('#bwsg-rows').on('click', '.bwsg-choose', function(e){
    e.preventDefault();
    var wrap = $(this).closest('.bwsg-imgwrap');
    var frame = wp.media({ title: 'Select Size Guide', multiple:false, library:{ type:'image' } });
    frame.on('select', function(){
      var att = frame.state().get('selection').first().toJSON();
      wrap.find('.bwsg-image-id').val(att.id);
      var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
      wrap.find('.bwsg-thumb').html('<img src="'+url+'" alt="">');
    });
    frame.open();
  });

  // Clear image
  $('#bwsg-rows').on('click', '.bwsg-clear', function(e){
    e.preventDefault();
    var wrap = $(this).closest('.bwsg-imgwrap');
    wrap.find('.bwsg-image-id').val('');
    wrap.find('.bwsg-thumb').html('<em>No image selected</em>');
  });

  // Product override
  $('#bwsg_override_choose').on('click', function(e){
    e.preventDefault();
    var frame = wp.media({ title: 'Select Size Guide', multiple:false, library:{ type:'image' } });
    frame.on('select', function(){
      var att = frame.state().get('selection').first().toJSON();
      $('#bwsg_override_id').val(att.id);
      var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
      $('#bwsg_override_thumb').html('<img style="max-width:120px;height:auto;border:1px solid #e5e7eb;border-radius:6px;padding:2px;background:#fff" src="'+url+'" alt="">');
    });
    frame.open();
  });

  $('#bwsg_override_clear').on('click', function(e){
    e.preventDefault();
    $('#bwsg_override_id').val('');
    $('#bwsg_override_thumb').html('<em>No image selected</em>');
  });
});
