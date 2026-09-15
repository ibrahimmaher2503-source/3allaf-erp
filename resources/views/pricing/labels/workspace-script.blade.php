<script>
    function labelWorkspace(initial, endpoint) {
        return {
            query: '',
            results: [],
            selected: initial,
            nextPage: null,
            loading: false,
            error: '',

            init() {
                this.selected = this.selected.map((product) => ({
                    ...product,
                    copies: String(product.copies || 1),
                    barcode_id: product.barcode_id || this.primary(product)?.id || '',
                }));
            },

            name(product) {
                return document.documentElement.lang.startsWith('ar')
                    ? product.name_ar
                    : (product.name_en || product.name_ar);
            },

            primary(product) {
                return (product.barcodes || []).find((barcode) => barcode.is_primary)
                    || (product.barcodes || [])[0];
            },

            has(id) {
                return this.selected.some((product) => product.id === id);
            },

            select(product) {
                if (!this.has(product.id)) {
                    this.selected.push({
                        ...product,
                        copies: '1',
                        barcode_id: this.primary(product)?.id || '',
                    });
                }
            },

            remove(id) {
                this.selected = this.selected.filter((product) => product.id !== id);
            },

            async search(exact = false, page = 1, append = false) {
                const query = this.query.trim();

                if (!query) {
                    this.results = [];
                    this.nextPage = null;
                    return;
                }

                this.loading = true;
                this.error = '';

                try {
                    const response = await fetch(
                        endpoint
                            + '?q=' + encodeURIComponent(query)
                            + '&page=' + page
                            + (exact ? '&exact=1' : ''),
                        { headers: { Accept: 'application/json' } },
                    );

                    if (!response.ok) {
                        throw new Error('Search request failed');
                    }

                    const payload = await response.json();
                    this.results = append ? [...this.results, ...payload.data] : payload.data;
                    this.nextPage = payload.next_page;

                    if (exact && payload.data.length === 1) {
                        this.select(payload.data[0]);
                    }
                } catch (error) {
                    this.error = @js(__('Product search could not be completed. Try again.'));
                } finally {
                    this.loading = false;
                }
            },

            more() {
                if (this.nextPage) {
                    this.search(false, this.nextPage, true);
                }
            },
        };
    }
</script>
