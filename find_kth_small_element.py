import heapq



def find_the_kth_biggest_element(arr, k):
    heap = []
    for i in range(len(arr)):
        heapq.heappush(heap, arr[i])
        if len(heap) > k :
            heapq.heappop(heap)
    return heap[0]

print(find_the_kth_biggest_element([7 ,10, 4, 3, 20 ,15], 3))
# 3 4 7 10 15 20 

def find_the_kth_smallest_element(arr, k):
    max_heap = []
    for i in range(len(arr)):
        heapq.heappush(max_heap, -arr[i])
        if len(max_heap) > k :
            heapq.heappop(max_heap)
    return -max_heap[0]

print(find_the_kth_smallest_element([7 ,10, 4, 3, 20 ,15], 3))
# 3 4 7 10 15 20 