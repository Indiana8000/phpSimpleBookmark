// ======================
// Global State
// ======================
let currentCategory = null;
let editmodeCategory = false;

// ======================
// API Helper
// ======================
function api(action, data = {}, cb) {
    $.ajax({
        url: 'api.php?action=' + action,
        method: 'POST',
        data: JSON.stringify(data),
        contentType: 'application/json',
        success: cb
    });
}



// ======================
// Render Functions
// ======================
function renderCategoryTitle(id, title) {
    return `<h5 class="mb-1">${title}</h5><ul id="c_${id}" class="list-group mb-2"></ul>`;
}

function renderCategory(c) {
    let title = c.name.split("/", 2);
    if(title.length > 1) title = title[1];
    else title = '-';
    return `
    <li class="list-group-item category-dragndrop-item pointer" data-id="${c.id}" data-view="${c.view}" data-icon="${c.icon}">
        <div class="list-content">
            <i class="bi ${c.icon} category-icon"></i>
            <span class="category-name">${title}</span>
        </div>
        <div class="list-hover-actions">
            <i class="bi bi-pencil text-warning pointer edit-category"></i>
            <i class="bi bi-trash  text-danger  pointer delete-category"></i>
        </div>
    </li>`;
}

function createCategoryEditHtml(title='', icon='bi-folder') {
    return `
        <div class="list-content">
            <strong class="category-name inline-edit m-1" contenteditable="true" title="Title">${title}</strong>
            <span   class="category-icon inline-edit m-1" contenteditable="true" title="Icon">${icon}</span>
        </div>
        <div class="list-hover-actions">
            <i class="bi bi-check-lg text-success pointer save-category"></i>
            <i class="bi bi-x-lg     text-danger  pointer cancel-category"></i>
        </div>
    `;
}

function renderItem(i) {
    return `
    <li class="list-group-item content-dragndrop-item" draggable="true" data-id="${i.id}">
        <div class="list-content list-content-item">
            <img src="${i.image}" class="item-img">
            <div class="item-main">
                <strong class="item-title  ">${i.title}</strong>
                <small  class="item-url    "><a href="${i.url}" target="_blank">${i.url}</a></small>
                <span   class="item-content">${i.content}</span>
            </div>
            <img src="${i.preview}" class="item-preview">
        </div>
        <div class="list-hover-actions">
            <i class="bi bi-pencil             text-warning pointer item-act-edit"></i>
            <i class="bi bi-arrow-left-square  text-info    pointer item-act-icon"       title="Get Icon"></i>
            <i class="bi bi-arrow-right-square text-info    pointer item-act-screenshot" title="Take Screenshot"></i>
            <i class="bi bi-trash              text-danger  pointer item-act-delete""></i>
        </div>
    </li>`;
}

function createItemEditHtml(title='', content='', image='', url='', preview='') {
    return `
    <div class="list-content list-content-item">
        <img src="${image}" class="item-img itemDropZone" id="itemImg">
        <div class="item-main">
            <strong class="item-title   inline-edit m-1" contenteditable="true" title="Title">${title}</strong>
            <small  class="item-url     inline-edit m-1" contenteditable="true" title="URL">${url}</small>
            <span   class="item-content inline-edit m-1" contenteditable="true" title="Description">${content}</span>
        </div>
        <img src="${preview}" class="item-preview itemDropZone" id="itemPrev">
    </div>
    <div class="list-hover-actions">
        <i class="bi bi-check-lg text-success pointer save-item"></i>
        <i class="bi bi-x-lg     text-danger  pointer cancel-item"></i>
    </div>
    `;
}

function renderIcon(i) {
    const filename = i.url.substring(i.url.lastIndexOf('/') + 1, i.url.indexOf('?') > 0 ? i.url.indexOf('?') : i.url.length);
    return `
    <li class="list-group-item">
        <img class="icon-img pointer" src="${i.url}"> ${filename} - ${i.width} x ${i.height}
    </li>`;
}


// ======================
// Load Data
// ======================
function loadCategories() {
    api('getCategories', {}, res => {
        $('#categoryList').empty();
        let title = "";
        let title_id = 1;
        res.forEach(c => {
            let x = c.name.split("/");
            c.view = c.view ?? 'list';
            if(x[0] != title) {
                title = x[0];
                title_id++;
                $('#categoryList').append(renderCategoryTitle(title_id, title));
            }
            if(!currentCategory) {
                currentCategory = c.id;
                loadItems(c.id, c.view);
            }
            $('#c_' + title_id).append(renderCategory(c))
        });
        if(currentCategory) $("#categoryList").find(`[data-id='${currentCategory}']`).addClass('category-active');
    });
}

function loadItems(categoryId, view) {
    api('getItems', { category_id: categoryId }, res => {
        $('#itemList').empty();
        setViewState(view);
        res.forEach(i => $('#itemList').append(renderItem(i)));
    });
}

// ======================
// Category Handling
// ======================
$(document).on('click', '#categoryList li', function(e) {
    const id = $(this).data('id');
    const view = $(this).data('view');
    if(id) {
        currentCategory = id;
        $("#categoryList").find("li.category-active").removeClass("category-active");   
        $(this).addClass('category-active');
        $('.sidebar').removeClass('show');
        loadItems(id, view);
    }
});

// Inline Create
$('#addCategory').on('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    if(editmodeCategory) return;
    editmodeCategory = true;
    const li = $('<li class="list-group-item list-group-item-dark editing"></li>');
    li.html(createCategoryEditHtml());

    if(currentCategory) $("#categoryList").find(`[data-id='${currentCategory}']`).closest('ul').append(li);
    else $('#categoryList ul:last').append(li);
    li.find('.category-name').focus();
});

// Edit Category
$(document).on('click', '.edit-category', function(e){
    e.preventDefault();
    e.stopPropagation();
    if(editmodeCategory) return;
    editmodeCategory = true;
    const li = $(this).closest('li');
    li.addClass('editing');
    li.html(createCategoryEditHtml(li.find('.category-name').text(), li.data('icon')));
    li.find('.category-name').focus();
});

// Save Category
$(document).on('click', '.save-category', function(e) {
    e.preventDefault();
    e.stopPropagation();
    editmodeCategory = false;
    const li = $(this).closest('li');
    const id = li.data('id');
    const icon = li.find('.category-icon').text().trim();
    let name = li.find('.category-name').text().trim();

    if(!name) return;
    if(name.indexOf("/") == -1) name = $(this).closest('ul').prev().text() + "/" + name;

    if(id) api('updateCategory', { id, name, icon }, loadCategories);
    else api('addCategory', { name, icon }, loadCategories);
});

// Cancel Category Edit
$(document).on('click', '.cancel-category', function(e) {
    e.preventDefault();
    e.stopPropagation();
    editmodeCategory = false;
    loadCategories();
});

// Delete Category
$(document).on('click', '.delete-category', function(e){
    e.preventDefault();
    e.stopPropagation();
    if(editmodeCategory) return;
    const li = $(this).closest('li');
    if(!confirm(`Kategorie ${li.find('.category-name').text()} löschen?`)) return;
    if(currentCategory == li.data('id')) {
        currentCategory = null;
        $('#itemList').empty();
    }
    api('deleteCategory', { id: li.data('id') }, loadCategories);
});

// ======================
// Items Handling
// ======================
$('#addItem').on('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $('.sidebar').removeClass('show');
    if(!currentCategory) return alert('Kategorie wählen');
    const li = $('<li class="list-group-item editing"></li>');
    setViewState();
    li.html(createItemEditHtml());
    $('#itemList').prepend(li);
    li.find('.item-url').focus();
});

$(document).on('click', '#itemList li', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $('.sidebar').removeClass('show');
    if(!$(this).hasClass('editing')) {
        const url = $(this).find('.item-url').text().trim();
        window.open(url, '_blank');
    }
});

// Save Item
$(document).on('click', '.save-item', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const li = $(this).closest('li');
    const id = li.data('id');

    const formData = new FormData();
    formData.append('action', li.data('id') ? 'updateItem' : 'addItem');
    formData.append('id', li.data('id') || '');
    formData.append('category_id', currentCategory);
    formData.append('title', li.find('.item-title').text().trim());
    formData.append('content', li.find('.item-content').text().trim());
    formData.append('url', li.find('.item-url').text().trim());

    const file1 = $('#itemImg').data('imageFile');
    if(file1) {
        formData.append('image', file1);
    }
    const file2 = $('#itemPrev').data('imageFile');
    if(file2) {
        formData.append('preview', file2);
    }

    if(id)
    $.ajax({
        url: 'api.php?action=updateItem',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: () => loadItems(currentCategory)
    });
    else
    $.ajax({
        url: 'api.php?action=addItem',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: () => loadItems(currentCategory)
    });
});

// Cancel Item
$(document).on('click', '.cancel-item', function(e){
    e.preventDefault();
    e.stopPropagation();
    loadItems(currentCategory);
});

// Edit Item
$(document).on('click', '.item-act-edit', function(e){
    e.preventDefault();
    e.stopPropagation();
    $('.sidebar').removeClass('show');
    const li = $(this).closest('li');
    const title = li.find('.item-title').text();
    const url = li.find('.item-url').text();
    const content = li.find('.item-content').text();
    const image = li.find('img.item-img').attr('src');
    const preview = li.find('img.item-preview').attr('src');
    setViewState();
    li.attr('draggable', false);
    li.addClass('editing');
    li.html(createItemEditHtml(title, content, image, url, preview));
    li.find('.item-title').focus();
});


// Delete Item
$(document).on('click', '.item-act-delete', function(e){
    e.preventDefault();
    e.stopPropagation();
    $('.sidebar').removeClass('show');
    const li = $(this).closest('li');
    if(!confirm('Eintrag löschen?')) return;
    api('deleteItem', { id: li.data('id') }, () => loadItems(currentCategory));
});

$(document).on('click', '.item-act-icon', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $('.sidebar').removeClass('show');
    const li = $(this).closest('li');
    const id = li.data('id');
    $('#iconLists').attr('data-id', id);
    $('#iconLists').empty();
    $('#iconModal .modal-title').html('Loading ...');
    $('#iconModal').modal('show');
    api('getIcons', { id: id }, res => {
        res.forEach(i => $('#iconLists').append(renderIcon(i)));
        $('#iconModal .modal-title').html('Select Icon');
    });
});
$(document).on('click', '.icon-img', function(e) {
    e.preventDefault();
    e.stopPropagation();
    api('setIcon', { id: $('#iconLists').attr('data-id'), url: $(this).attr('src') }, () => {
        loadItems(currentCategory);
        $('#iconModal').modal('hide');
    });
});

$(document).on('click', '.item-act-screenshot', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $('.sidebar').removeClass('show');
    const li = $(this).closest('li');
    const id = li.data('id');
    $('#screenshotModalPreview').attr('src', '');
    $('#screenshotModal .modal-title').html('Loading ...');
    $('#deleteScreenshot').hide();
    $('#saveScreenshot').hide();
    $('#screenshotModal').modal('show');
    api('getScreenshot', { id: id }, res => {
        if(res.image) {
            $('#screenshotModalPreview').data('id', id);
            $('#screenshotModalPreview').data('imageFile', res.image);
            $('#screenshotModalPreview').attr('src', 'data:image/png;base64,' + res.image);
            $('#screenshotModal .modal-title').html('Screenshot Preview');
            $('#saveScreenshot').show();
        } else {
            $('#screenshotModal').modal('hide');
            alert(res.error);
        }
    });
});
$(document).on('click', '#saveScreenshot', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const formData = new FormData();
    formData.append('action', 'setScreenshot');
    formData.append('id', $('#screenshotModalPreview').data('id'));

    const file2 = $('#screenshotModalPreview').data('imageFile');
    if(file2) {
        formData.append('preview', file2);
        $.ajax({
            url: 'api.php?action=setScreenshot',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: () => {$('#screenshotModal').modal('hide'); loadItems(currentCategory);}
        });
    } else {
        alert('Error: Missing Screenshot!');
    }
});

$(document).on('click', '.item-preview', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $('.sidebar').removeClass('show');
    const li = $(this).closest('li');
    const id = li.data('id');
    $('#screenshotModal .modal-title').html('Screenshot');
    $('#deleteScreenshot').show();
    $('#saveScreenshot').hide();
    $('#screenshotModalPreview').data('id', id)
    $('#screenshotModalPreview').attr('src', $(this).attr('src'));
    $('#screenshotModal').modal('show');
});
$(document).on('click', '#deleteScreenshot', function(e) {
    e.preventDefault();
    e.stopPropagation();
    api('deleteScreenshot', { id: $('#screenshotModalPreview').data('id') }, res => {
        $('#screenshotModal').modal('hide');
        loadItems(currentCategory);
    });
});



// ======================
// Items Handling - Drag n Drop Image File
// ======================
$(document).on('dragover', '.itemDropZone', function(e){
    e.preventDefault();
    e.stopPropagation();
    $(this).addClass('drag-over');
});

$(document).on('dragleave', '.itemDropZone', function(e){
    e.preventDefault();
    e.stopPropagation();
    $(this).removeClass('drag-over');
});

$(document).on('drop', '.itemDropZone', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).removeClass('drag-over');

    const file = e.originalEvent.dataTransfer.files[0];
    if (!file || !file.type.startsWith('image/')) return;

    $(this).data('imageFile', file);

    const reader = new FileReader();
    reader.onload = evt => {
        $(this)
            .attr('src', evt.target.result)
            .show();
    };
    reader.readAsDataURL(file);
});



// ======================
// Items Handling - Drag n Drop Category
// ======================
let draggedItemId = null;

$(document).on('dragstart', '.content-dragndrop-item', function(e) {
    draggedItemId = $(this).data('id');
    e.originalEvent.dataTransfer.effectAllowed = 'move';
    console.log("dragstart - " + draggedItemId);
});
$(document).on('dragend', '.content-dragndrop-item', function(e) {
    draggedItemId = null;
});

$(document).on('dragover', '.category-dragndrop-item', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).addClass('category-dragover');
});
$(document).on('dragleave', '.category-dragndrop-item', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).removeClass('category-dragover');
});

$(document).on('drop', '.category-dragndrop-item', function(e) {
    $(this).removeClass('category-dragover');
    api('updateItemCategory', { id: draggedItemId, category_id: $(this).data('id') }, () => {
        loadItems(currentCategory);
        $('#iconModal').modal('hide');
    });
});



// ======================
// 
// ======================
function setViewState(view = 'list') {
    // Set Toggle-Button
    $('.view-toggle').removeClass('active');
    $('.view-toggle[data-view=' + view + ']').addClass('active');

    // Set View
    $('#itemList')
        .removeClass('view-list view-grid')
        .addClass('view-' + view);
}

$('.view-toggle').on('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $('.sidebar').removeClass('show');
    const view = $(this).data('view');
    setViewState(view);
    api('updateCategoryView', { id: currentCategory, view: view }, () => {
        $('#categoryList .list-group-item[data-id=' + currentCategory + ']').data('view', view);
    });
});



// ======================
// Mobile-View Sidebar Button
// ======================
$('#sidebar-btn').on('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $('.sidebar').addClass('show');
});

$(document).on('click', function(e) {
    $('.sidebar').removeClass('show');
});



// ======================
// Test Button
// ======================
$('#testbutton').on('click', () => {
    console.log("Clicked!");
    alert("Clicked!");
});



// ======================
// Init
// ======================
$(document).ready(loadCategories);