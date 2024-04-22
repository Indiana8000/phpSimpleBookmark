// ***
// *** phpSimpleBookmar ... yehaaa!
// ***

// Categories
var myCategories;
var myCagegoryActive;
function getCategoryList() {
  $.ajax({
		url: "ajax.php",
		type: "POST",
		dataType: "json",
		data: {
			action: "getCategoryList"
		},
		error: function(jqXHR, textStatus, errorThrown) {
			alert(errorThrown);
		},
		success: function(data, textStatus, jqXHR) {
      if(data.status != 0) {
        alert("Ajax Error: " + data.status + " - " + data.message);
      } else {
        console.log('getCategoryList - ' + data.categories.length);
        myCategories = data.categories.slice();
        for(var i = 0; i < myCategories.length; i++) {
          myCategories[i].ca_id  = parseInt(myCategories[i].ca_id);
          myCategories[i].ca_pos = parseInt(myCategories[i].ca_pos);
          createCategoryCard(myCategories[i]);
          $('#bm_edit_category').append(new Option(myCategories[i].ca_title, myCategories[i].ca_id));
          if(!myCagegoryActive) {
            myCagegoryActive = myCategories[i].ca_id;
            $('#ca_category').find("[data-caid='" + myCagegoryActive + "']").children(".ca_category_card_title").addClass("ca_category_active");
          }
        }
        getBookmarkList();
      }
    }
	});
}

function createCategoryCard(category) {
  var card = $('#ca_category').find("[data-caid='" + category.ca_id + "']");
  if(card.length == 0) {
    var newCategory = '<div class="ca_category_card" data-caid="' + category.ca_id + '"><i class="ca_category_card_icon bi"></i><div class="ca_category_card_title">TITLE</div></div>';
    $('#ca_category').append(newCategory);
    card = $('#ca_category').find("[data-caid='" + category.ca_id + "']");
  }
  card.children(".ca_category_card_title").html(category.ca_title);
  card.children(".ca_category_card_icon").removeClass().addClass('ca_category_card_icon bi ' + category.ca_icon)
  card.offset({left: card.offset().left, top: 25 + category.ca_pos * 125});
}

function saveCategoryCard(category) {
  $.ajax({
		url: "ajax.php",
		type: "POST",
		dataType: "json",
		data: {
			action: "saveCategory",
      category: JSON.stringify(category)
		},
		error: function(jqXHR, textStatus, errorThrown) {
			alert(errorThrown);
		},
		success: function(data, textStatus, jqXHR) {
      if(data.status != 0) {
        alert("Ajax Error: " + data.status + " - " + data.message);
      } else {
        category = data.category;
        category.ca_id  = parseInt(category.ca_id);
        category.ca_pos = parseInt(category.ca_pos);
        var ca_elem = myCategories.findIndex((o) => { return o['ca_id'] === category.ca_id});
        if(ca_elem >= 0) {
          myCategories[ca_elem] = category;
        } else {
          myCategories.push(category);
        }
        createCategoryCard(category);
        $('#bm_edit_category').empty();
        for(var i = 0; i < myCategories.length; i++) {
          $('#bm_edit_category').append(new Option(myCategories[i].ca_title, myCategories[i].ca_id));
        }

      }
    }
	});
}



// Bookmarks
var myBookmarks;
function getBookmarkList() {
  $.ajax({
		url: "ajax.php",
		type: "POST",
		dataType: "json",
		data: {
			action: "getBookmarkList"
		},
		error: function(jqXHR, textStatus, errorThrown) {
			alert(errorThrown);
		},
		success: function(data, textStatus, jqXHR) {
      if(data.status != 0) {
        alert("Ajax Error: " + data.status + " - " + data.message);
      } else {
        console.log('getBookmarkList - ' + data.bookmarks.length);
        myBookmarks = data.bookmarks.slice();
        for(var i = 0; i < myBookmarks.length; i++) {
          myBookmarks[i].bm_id  = parseInt(myBookmarks[i].bm_id);
          myBookmarks[i].bm_x   = parseInt(myBookmarks[i].bm_x);
          myBookmarks[i].bm_y   = parseInt(myBookmarks[i].bm_y);
          $('<img/>')[0].src = 'icons/' + myBookmarks[i].bm_icon + '.png';
          createBookmarkCard(myBookmarks[i]);
        }
      }
    }
	});
}

function createBookmarkCard(bookmark) {
  var card = $('#bm_bookmark').find("[data-bmid='" + bookmark.bm_id + "']");
  if(card.length == 0) {
    var newcard = '<div class="bm_bookmark_card" data-bmid="'+ bookmark.bm_id +'"><a href="" target="_blank"><img class="bm_bookmark_card_icon" /></a><div class="bm_bookmark_card_title">TITLE</div></div>';
    $('#bm_bookmark').append(newcard);
    card = $('#bm_bookmark').find("[data-bmid='" + bookmark.bm_id + "']");
  }
  card.children(".bm_bookmark_card_title").html(bookmark.bm_title);
  card.find(".bm_bookmark_card_icon").attr('src', 'icons/' + bookmark.bm_icon + '.png');
  card.children("a").attr('href', bookmark.bm_url);
  if(bookmark.bm_url.lastIndexOf("javascript", 0) === 0) {
    card.children("a").attr('target', "");
  } else {
    card.children("a").attr('target', "_blank");
  }
  if(bookmark.bm_category == myCagegoryActive) {
    card.show();
    card.offset({left: 200 + bookmark.bm_x * 160, top: 25 + bookmark.bm_y * 160});
  } else {
    card.hide();
  }
}

function saveBookmarkCard(bookmark) {
  $.ajax({
		url: "ajax.php",
		type: "POST",
		dataType: "json",
		data: {
			action: "saveBookmark",
      bookmark: JSON.stringify(bookmark)
		},
		error: function(jqXHR, textStatus, errorThrown) {
			alert(errorThrown);
		},
		success: function(data, textStatus, jqXHR) {
      if(data.status != 0) {
        alert("Ajax Error: " + data.status + " - " + data.message);
      } else {
        bookmark = data.bookmark;
        bookmark.bm_id = parseInt(bookmark.bm_id);
        bookmark.bm_x  = parseInt(bookmark.bm_x);
        bookmark.bm_y  = parseInt(bookmark.bm_y);
        var bm_elem = myBookmarks.findIndex((o) => { return o['bm_id'] === bookmark.bm_id });
        if(bm_elem >= 0) {
          myBookmarks[bm_elem] = bookmark;
        } else {
          myBookmarks.push(bookmark);
        }
        createBookmarkCard(bookmark);
      }
    }
	});
}

// Global Variables
var editMode = false;

// DOM Ready
$(document).ready(function() {
  var bm_moving;
  var bm_offset;
  $('#ca_edit_new').hide();
  $('#bm_edit_new').hide();
  $('#settings_open').hide();
  getCategoryList();

  // Settings - Click
  $('#switch').on("change", function(event) {
    //console.log("Checkbox: " + this.checked);
    editMode = this.checked;
    if(editMode) {
      $('.ca_category_card_title').addClass('cursor_move');
      $('.bm_bookmark_card_title').addClass('cursor_move');
      $('#ca_edit_new').show();
      $('#bm_edit_new').show();
      $('#settings_open').show();
    } else {
      $('.ca_category_card_title').removeClass('cursor_move');
      $('.bm_bookmark_card_title').removeClass('cursor_move');
      $('#ca_edit_new').hide();
      $('#bm_edit_new').hide();
      $('#settings_open').hide();
    }
    bm_moving = null;
    bm_offset = null;
});

  // Category - Click
  $(document).on("click", ".ca_category_card", function(event) {
    event.preventDefault();
    event.stopPropagation();
    var ca_id = parseInt($(this).attr('data-caid'));
    //console.log("Category Clicked: " + ca_id);
    if(editMode) {
      if(!bm_moving) {
        var ca_elem = myCategories.find((o) => { return o['ca_id'] === ca_id });
        $('#ca_modal').attr('data-caid', ca_id)
        $('#ca_edit_title').val(ca_elem.ca_title);
        $('#ca_edit_icon').val(ca_elem.ca_icon);
        $('#ca_modal').modal('show');
      }
    } else {
      myCagegoryActive = $(this).attr('data-caid');
      for(var i = 0;i < myBookmarks.length;i++)
      createBookmarkCard(myBookmarks[i]);
      $('.ca_category_card_title').removeClass("ca_category_active");
      $('#ca_category').find("[data-caid='" + myCagegoryActive + "']").children(".ca_category_card_title").addClass("ca_category_active");
    }
  });

  // Category - Save
  $("#ca_edit_save").on("click", function(event) {
    event.preventDefault();
    event.stopPropagation();
    var ca_id = parseInt($('#ca_modal').attr('data-caid'));
    var ca_elem;
    if(ca_id > 0) {
      ca_elem = myCategories.find((o) => { return o['ca_id'] === ca_id });
      ca_elem.ca_title = $('#ca_edit_title').val();
      ca_elem.ca_icon = $('#ca_edit_icon').val();
    } else {
      ca_elem = {ca_id: 0, ca_title: $('#ca_edit_title').val(), ca_icon: $('#ca_edit_icon').val(), ca_pos: myCategories.length};
    }
    saveCategoryCard(ca_elem);
    $('#ca_modal').modal('hide');
  });

  // Category - New
  $("#ca_edit_new").on("click", function(event) {
    event.preventDefault();
    event.stopPropagation();
    $('#ca_modal').attr('data-caid', 0)
    $('#ca_edit_title').val('');
    $('#ca_edit_icon').val('');
    $('#ca_modal').modal('show');
  });

  // Category - Modal
  $('#ca_modal').on("shown.bs.modal", function(event) {
    $('#ca_edit_title').focus();
  });

  // Bookmark - Click
  $(document).on("click", ".bm_bookmark_card", function(event) {
    var bm_id = parseInt($(this).attr('data-bmid'));
    console.log("Bookmark Clicked: " + bm_id);
    var bm_elem = myBookmarks.find((o) => { return o['bm_id'] === bm_id });
    if(editMode) {
      event.preventDefault();
      event.stopPropagation();
      if(!bm_moving) {
        $('#bm_modal').attr('data-bmid', bm_id);
        $('#bm_edit_title').val(bm_elem.bm_title);
        $('#bm_edit_url').val(bm_elem.bm_url);
        $('#bm_edit_category').val(bm_elem.bm_category);
        $('#bm_modal').modal('show');
      }
    } else {
      // Alternative to use Hyperlinks
      //window.open(bm_elem.bm_url, '_blank');
    }
  });

  // Bookmark - Save
  $('#bm_edit_save').on("click", function(event) {
    event.preventDefault();
    event.stopPropagation();
    var bm_id = parseInt($('#bm_modal').attr('data-bmid'));
    var bm_elem;
    if(bm_id > 0) {
      bm_elem = myBookmarks.find((o) => { return o['bm_id'] === bm_id });
      bm_elem.bm_title = $('#bm_edit_title').val();
      bm_elem.bm_url = $('#bm_edit_url').val();
      bm_elem.bm_category = $('#bm_edit_category').val();
    } else {
      // TBD Find free space
      bm_elem = {bm_id: bm_id, bm_title: $('#bm_edit_title').val(), bm_url: $('#bm_edit_url').val(),bm_icon: "", bm_category: $('#bm_edit_category').val(), bm_x: 2, bm_y: 2};
    }
    saveBookmarkCard(bm_elem);
    $('#bm_modal').modal('hide');
  });

  // Bookmark - New
  $('#bm_edit_new').on("click", function(event) {
    event.preventDefault();
    event.stopPropagation();
    $('#bm_modal').attr('data-bmid', 0);
    $('#bm_edit_title').val('');
    $('#bm_edit_url').val('');
    $('#bm_edit_category').val(myCagegoryActive);
    $('#bm_modal').modal('show');
  });


  // Bookmark - Modal
  $('#bm_modal').on("shown.bs.modal", function(event) {
    $('#bm_edit_title').focus();
  });

  // Drag & Drop
  $(document).on("mousedown", ".ca_category_card_title", function(event) {
    if(editMode) {
      event.preventDefault();
      event.stopPropagation();
      bm_moving = this;
      bm_offset = {left: event.clientX, top: event.clientY};
      $(bm_moving).parent().css('z-index', 99);
    }
  });

  $(document).on("mousedown", ".bm_bookmark_card_title", function(event) {
    if(editMode) {
      event.preventDefault();
      event.stopPropagation();
      bm_moving = this;
      bm_offset = {left: event.clientX, top: event.clientY};
      $(bm_moving).parent().css('z-index', 99);
    }
  });

  $(document).on("mousemove", function(event) {
    if(editMode) {
      event.preventDefault();
      event.stopPropagation();
      if(bm_moving) {
        element = $(bm_moving).parent();
        if(element.hasClass('ca_category_card')) {
          element.offset({left: element.offset().left, top: element.offset().top - (bm_offset.top - event.clientY)});
        } else if(element.hasClass('bm_bookmark_card')) {
          element.offset({left: element.offset().left - (bm_offset.left - event.clientX) , top: element.offset().top - (bm_offset.top - event.clientY)});
        }
        bm_offset = {left: event.clientX, top: event.clientY};
      }
    }
  });

  $(document).on("mouseup", function(event) {
    if(editMode) {
      event.preventDefault();
      event.stopPropagation();
      element = $(bm_moving).parent();

      if(element.hasClass('ca_category_card')) {
        var ca_id = parseInt(element.attr('data-caid'));
        var ca_elem = myCategories.find((o) => { return o['ca_id'] === ca_id });
        var pos_cur = ca_elem.ca_pos;
        
        var pos = element.offset().top;
        pos_new = Math.round((pos - 25) / 125);
        if(pos_new < 0) pos_new = 0;
        if(pos_new > myCategories.length -1) pos_new = myCategories.length -1;
  
        var pos_mod = 0;
        if(pos_new > pos_cur) pos_mod = -1;
        if(pos_new < pos_cur) pos_mod = +1;
  
        if(pos_mod != 0) {
          for(var i = 0; i < myCategories.length; i++) {
            if(myCategories[i].ca_id != ca_id && myCategories[i].ca_pos.between(pos_cur,pos_new)) {
              myCategories[i].ca_pos += pos_mod;
              $('body').find("[data-caid='" + myCategories[i].ca_id + "']").animate({top: 25 +  myCategories[i].ca_pos * 125}, 200);
              saveCategoryCard(myCategories[i]);
            }
          }
          ca_elem.ca_pos = pos_new;
          element.animate({"z-index": 9, top: 25 +  pos_new * 125}, 200);
          saveCategoryCard(ca_elem);
        } else {
          element.animate({"z-index": 9, top: 25 +  pos_new * 125}, 200);
        }

      } else if(element.hasClass('bm_bookmark_card')) {
        var bm_id = parseInt(element.attr('data-bmid'));
        var bm_elem = myBookmarks.find((o) => { return o['bm_id'] === bm_id });
  
        var pos_x = element.offset().left;
        pos_x = Math.round((pos_x - 200) / 160);
        var pos_y = element.offset().top;
        pos_y = Math.round((pos_y - 25) / 160);
        if(bm_elem.bm_x != pos_x || bm_elem.bm_y != pos_y) {
          var bm_overlap = myBookmarks.find((o) => { return o['bm_x'] === pos_x && o['bm_y'] === pos_y && o['bm_category'] === bm_elem.bm_category });
          if(bm_overlap) {
            bm_overlap.bm_x = bm_elem.bm_x;
            bm_overlap.bm_y = bm_elem.bm_y;
            $('#bm_bookmark').find("[data-bmid='" + bm_overlap.bm_id + "']").animate({left: 0 + bm_overlap.bm_x * 160, top: 25 + bm_overlap.bm_y * 160}, 200);
            saveBookmarkCard(bm_overlap);
          }
          bm_elem.bm_x = pos_x;
          bm_elem.bm_y = pos_y;
          element.animate({"z-index": 9, left: 0 + pos_x * 160, top: 25 + pos_y * 160}, 200);
          saveBookmarkCard(bm_elem);
        } else {
          element.animate({"z-index": 9, left: 0 + pos_x * 160, top: 25 + pos_y * 160}, 200);
        }
      }
      window.setTimeout(function() {
        bm_moving = null;
        bm_offset = null;
      }, 100);
    }
  }); // END mouseup

});

Number.prototype.between = function(a, b) {
  var min = Math.min(a, b),
    max = Math.max(a, b);

  return this >= min && this <= max;
};
