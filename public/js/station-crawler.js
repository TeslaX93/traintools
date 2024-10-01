function filterAndSimplifyArray(arr) {
    return arr
        .filter(item => item.h.startsWith('51')) // Filtruj elementy, gdzie 'h' zaczyna się od "51"
        .map(item => ({ n: item.n, h: item.h })); // Mapuj na nowe obiekty zawierające tylko 'n' i 'h'
}