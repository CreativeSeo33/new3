import { reactive, computed, readonly } from 'vue';
import type { ApiResource, CrudOptions, CrudState } from '@admin/types/api';
import type { BaseRepository } from '@admin/repositories/BaseRepository';

export function useCrud<T extends ApiResource>(repository: BaseRepository<T>) {
  const state = reactive({
    items: [] as T[],
    item: null as T | null,
    totalItems: 0,
    loading: false,
    error: null as string | null,
    pagination: {
      page: 1,
      itemsPerPage: 10,
      totalPages: 0,
    },
  }) as unknown as CrudState<T>;

  const hasItems = computed(() => state.items.length > 0);
  const hasNextPage = computed(() => state.pagination.page < state.pagination.totalPages);
  const hasPrevPage = computed(() => state.pagination.page > 1);

  const setLoading = (loading: boolean) => {
    state.loading = loading;
  };

  const setError = (error: string | null) => {
    state.error = error;
  };

  const fetchAll = async (options: CrudOptions = {}) => {
    try {
      setLoading(true);
      setError(null);

      const response = (await repository.findAll({
        page: state.pagination.page,
        itemsPerPage: state.pagination.itemsPerPage,
        ...options,
      })) as unknown as any;

      const items: T[] = Array.isArray(response)
        ? (response as T[])
        : ((response?.['hydra:member'] ?? response?.member ?? []) as T[]);
      const totalItems: number = Array.isArray(response)
        ? items.length
        : Number(
            response?.['hydra:totalItems'] ??
            response?.totalItems ??
            items.length,
          );

      state.items = items;
      state.totalItems = totalItems;
      state.pagination.totalPages = Math.ceil(
        (state.totalItems || 0) / (state.pagination.itemsPerPage || 10),
      );

      if (options.page) state.pagination.page = options.page;
      if (options.itemsPerPage) state.pagination.itemsPerPage = options.itemsPerPage;
    } catch (error: any) {
      setError(error instanceof Error ? error.message : 'Failed to fetch items');
    } finally {
      setLoading(false);
    }
  };

  const fetchById = async (id: string | number) => {
    try {
      setLoading(true);
      setError(null);
      state.item = (await repository.findById(id)) as T;
    } catch (error: any) {
      setError(error instanceof Error ? error.message : 'Failed to fetch item');
      state.item = null;
    } finally {
      setLoading(false);
    }
  };

  const create = async (data: Partial<T>) => {
    try {
      setLoading(true);
      setError(null);
      const newItem = (await repository.create(data)) as T;
      state.items.unshift(newItem);
      state.totalItems += 1;
      return newItem;
    } catch (error: any) {
      setError(error instanceof Error ? error.message : 'Failed to create item');
      throw error;
    } finally {
      setLoading(false);
    }
  };

  const update = async (id: string | number, data: Partial<T>) => {
    try {
      setLoading(true);
      setError(null);
      const updatedItem = (await repository.partialUpdate(id, data)) as T;
      const index = state.items.findIndex((item) => item.id === id);
      if (index !== -1) {
        state.items[index] = updatedItem;
      }
      if (state.item?.id === id) {
        state.item = updatedItem;
      }
      return updatedItem;
    } catch (error: any) {
      setError(error instanceof Error ? error.message : 'Failed to update item');
      throw error;
    } finally {
      setLoading(false);
    }
  };

  const remove = async (id: string | number) => {
    try {
      setLoading(true);
      setError(null);
      await repository.delete(id);
      state.items = state.items.filter((item) => item.id !== id);
      state.totalItems -= 1;
      if (state.item?.id === id) {
        state.item = null;
      }
    } catch (error: any) {
      setError(error instanceof Error ? error.message : 'Failed to delete item');
      throw error;
    } finally {
      setLoading(false);
    }
  };

  const bulkRemove = async (ids: Array<string | number>) => {
    try {
      setLoading(true);
      setError(null);
      await repository.bulkDelete(ids);
      state.items = state.items.filter((item) => !ids.includes(item.id!));
      state.totalItems -= ids.length;
    } catch (error: any) {
      setError(error instanceof Error ? error.message : 'Failed to delete items');
      throw error;
    } finally {
      setLoading(false);
    }
  };

  const nextPage = () => {
    if (hasNextPage.value) {
      state.pagination.page += 1;
      fetchAll();
    }
  };

  const prevPage = () => {
    if (hasPrevPage.value) {
      state.pagination.page -= 1;
      fetchAll();
    }
  };

  const goToPage = (page: number) => {
    if (page >= 1 && page <= state.pagination.totalPages) {
      state.pagination.page = page;
      fetchAll();
    }
  };

  const setItemsPerPage = (itemsPerPage: number) => {
    state.pagination.itemsPerPage = itemsPerPage;
    state.pagination.page = 1;
    fetchAll();
  };

  const reset = () => {
    state.items = [];
    state.item = null;
    state.totalItems = 0;
    state.loading = false;
    state.error = null;
    state.pagination.page = 1;
  };

  return {
    state: readonly(state),
    hasItems,
    hasNextPage,
    hasPrevPage,
    fetchAll,
    fetchById,
    create,
    update,
    remove,
    bulkRemove,
    nextPage,
    prevPage,
    goToPage,
    setItemsPerPage,
    reset,
  };
}


