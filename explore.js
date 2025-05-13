document.addEventListener('DOMContentLoaded', function() {
    // Book filtering functionality
    const searchInput = document.getElementById('book-search');
    const genreFilter = document.getElementById('genre-filter');
    const priceFilter = document.getElementById('price-filter');
    const sortBy = document.getElementById('sort-by');
    const bookItems = document.querySelectorAll('.book-item');

    // Search functionality
    if (searchInput) {
        searchInput.addEventListener('input', filterBooks);
    }

    // Filter change events
    if (genreFilter) {
        genreFilter.addEventListener('change', filterBooks);
    }

    if (priceFilter) {
        priceFilter.addEventListener('change', filterBooks);
    }

    if (sortBy) {
        sortBy.addEventListener('change', sortBooks);
    }

    // Filter books based on search and filters
    function filterBooks() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const genre = genreFilter ? genreFilter.value : '';
        const price = priceFilter ? priceFilter.value : '';

        bookItems.forEach(book => {
            const title = book.querySelector('h3').textContent.toLowerCase();
            const author = book.querySelector('.author').textContent.toLowerCase();
            const bookGenre = book.dataset.genre;
            const bookPrice = parseFloat(book.dataset.price);

            let showBook = true;

            // Check search term
            if (searchTerm && !title.includes(searchTerm) && !author.includes(searchTerm)) {
                showBook = false;
            }

            // Check genre filter
            if (genre && bookGenre !== genre) {
                showBook = false;
            }

            // Check price filter
            if (price) {
                const [min, max] = price.split('-').map(val => val === '+' ? Infinity : parseFloat(val));
                if (bookPrice < min || (max !== Infinity && bookPrice > max)) {
                    showBook = false;
                }
            }

            book.style.display = showBook ? 'block' : 'none';
        });
    }

    // Sort books based on selection
    function sortBooks() {
        const sortValue = sortBy ? sortBy.value : 'popular';
        const booksGrid = document.querySelector('.books-grid');
        const booksArray = Array.from(bookItems);

        switch (sortValue) {
            case 'price-low':
                booksArray.sort((a, b) => parseFloat(a.dataset.price) - parseFloat(b.dataset.price));
                break;
            case 'price-high':
                booksArray.sort((a, b) => parseFloat(b.dataset.price) - parseFloat(a.dataset.price));
                break;
            case 'newest':
                // For demo purposes, we'll just randomize
                booksArray.sort(() => Math.random() - 0.5);
                break;
            default:
                // Popular - default order
                break;
        }

        // Re-append sorted items
        booksArray.forEach(book => {
            booksGrid.appendChild(book);
        });
    }

    // Add to cart functionality
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const bookItem = this.closest('.book-item');
            const title = bookItem.querySelector('h3').textContent;
            const price = bookItem.querySelector('.price').textContent;
            
            alert(`Added to cart: ${title} - ${price}`);
        });
    });

    // Pagination functionality
    const pageButtons = document.querySelectorAll('.page-btn');
    pageButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (!this.classList.contains('active') && !this.classList.contains('next')) {
                document.querySelector('.page-btn.active').classList.remove('active');
                this.classList.add('active');
                
                // In a real application, this would load new books
                // For demo purposes, we'll just scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    });
});