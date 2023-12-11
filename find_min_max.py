def find_the_min_and_max(arr):
    for i in range(len(arr)):
        minimum = maximum = arr[0]
        if arr[i] < minimum:
            minimum = arr[i]
        if arr[i] > maximum:
            maximum = arr[i] 
    return minimum, maximum

print(find_the_min_and_max([1,2,3,4,5]))